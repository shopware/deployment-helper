<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Struct\OneTimeTaskWhen;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpgradeManager
{
    public function __construct(
        private readonly ShopwareState $state,
        private readonly ProcessHelper $processHelper,
        private readonly OpenSearchHelper $openSearchHelper,
        private readonly PluginHelper $pluginHelper,
        private readonly AppHelper $appHelper,
        private readonly HookExecutor $hookExecutor,
        private readonly OneTimeTasks $oneTimeTasks,
        private readonly ProjectConfiguration $configuration,
        private readonly AccountService $accountService,
        private readonly TrackingService $trackingService,
    ) {
    }

    public function run(RunConfiguration $configuration, OutputInterface $output): void
    {
        $this->processHelper->setTimeout($configuration->timeout);

        $this->hookExecutor->execute(HookExecutor::HOOK_PRE_UPDATE);

        // Execute one-time tasks that should run before the update
        $this->oneTimeTasks->execute($output, OneTimeTaskWhen::BEFORE);

        if ($this->configuration->maintenance->enabled) {
            $this->state->enableMaintenanceMode();

            $output->writeln('Maintenance mode is enabled, clearing cache to make sure it is visible');
            $this->processHelper->console(['cache:pool:clear', 'cache.http', 'cache.object']);
        }

        $output->writeln('Shopware is installed, running update tools');

        $this->ensureOpenSearchIsReady($output);

        $this->processHelper->console(['messenger:setup-transports']);

        $previousVersion = $this->state->getPreviousVersion();
        $currentVersion = $this->state->getCurrentVersion();
        if ($previousVersion !== $currentVersion) {
            $output->writeln(\sprintf('Updating Shopware from %s to %s', $previousVersion, $currentVersion));

            $additionalUpdateParameters = [];

            if ($configuration->skipAssetsInstall) {
                $additionalUpdateParameters[] = '--skip-asset-build';
            }

            $took = microtime(true);

            $this->processHelper->console(['system:update:finish', ...$additionalUpdateParameters]);

            $this->state->setVersion($currentVersion);

            $this->trackingService->track('upgrade', [
                'took' => microtime(true) - $took,
                'previous_shopware_version' => $previousVersion,
            ]);
        }

        $salesChannelUrl = EnvironmentHelper::getVariable('SALES_CHANNEL_URL');

        if ($salesChannelUrl !== null && $this->state->isStorefrontInstalled() && !$this->state->isSalesChannelExisting($salesChannelUrl)) {
            $this->processHelper->console(['sales-channel:create:storefront', '--name=Storefront', '--url=' . UrlHelper::normalizeSalesChannelUrl($salesChannelUrl)]);
        }

        $this->processHelper->console(['plugin:refresh']);

        if ($this->state->isStorefrontInstalled()) {
            $this->processHelper->console(['theme:refresh']);
        }

        $this->processHelper->console(['scheduled-task:register']);
        $this->processHelper->console(['messenger:stop-workers']);

        $this->pluginHelper->installPlugins($output, $configuration->skipAssetsInstall);
        $this->pluginHelper->updatePlugins($output, $configuration->skipAssetsInstall);
        $this->pluginHelper->deactivatePlugins($output, $configuration->skipAssetsInstall);
        $this->pluginHelper->removePlugins($output, $configuration->skipAssetsInstall);

        if ($this->configuration->store->licenseDomain !== '') {
            $this->accountService->refresh(new SymfonyStyle(new ArgvInput([]), $output), $currentVersion, $this->configuration->store->licenseDomain);
        }

        $this->appHelper->installApps();
        $this->appHelper->updateApps();
        $this->appHelper->deactivateApps();
        $this->appHelper->removeApps();

        if (!$configuration->skipThemeCompile) {
            $took = microtime(true);
            $this->processHelper->console(['theme:compile', '--active-only']);
            $this->trackingService->track('theme_compiled', ['took' => microtime(true) - $took]);
        }

        // Execute one-time tasks that should run after the update
        $this->oneTimeTasks->execute($output, OneTimeTaskWhen::AFTER);

        $this->hookExecutor->execute(HookExecutor::HOOK_POST_UPDATE);

        if ($this->configuration->maintenance->enabled) {
            $this->state->disableMaintenanceMode();

            $output->writeln('Maintenance mode is disabled, clearing cache to make sure the storefront is visible again');
            $this->processHelper->console(['cache:pool:clear', 'cache.http', 'cache.object']);
        }
    }

    private function ensureOpenSearchIsReady(OutputInterface $output): void
    {
        if (!$this->isOpenSearchPreparationEnabled()) {
            return;
        }

        $action = $this->openSearchHelper->prepareShopIndex();

        if ($action === OpenSearchHelper::SHOP_INDEX_ACTION_NONE) {
            return;
        }

        if ($action === OpenSearchHelper::SHOP_INDEX_ACTION_UPDATE_MAPPING) {
            $output->writeln('Running OpenSearch mapping update because the shop index mapping is incomplete');
            $this->processHelper->console(['es:mapping:update']);

            return;
        }

        $output->writeln('Running OpenSearch indexing because the shop alias or index settings are incomplete');
        $this->processHelper->console(['es:index', '--no-queue']);
    }

    private function isOpenSearchPreparationEnabled(): bool
    {
        if (EnvironmentHelper::hasVariable('SHOPWARE_DEPLOYMENT_OPENSEARCH_PREPARE_INDEX')) {
            return EnvironmentHelper::getVariable('SHOPWARE_DEPLOYMENT_OPENSEARCH_PREPARE_INDEX', '0') === '1';
        }

        return $this->configuration->openSearch->indexIfEmpty;
    }
}
