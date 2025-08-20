<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpgradeManager
{
    public function __construct(
        private readonly ShopwareState $state,
        private readonly ProcessHelper $processHelper,
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

        if ($this->configuration->maintenance->enabled) {
            $this->state->enableMaintenanceMode();

            $output->writeln('Maintenance mode is enabled, clearing cache to make sure it is visible');
            $this->processHelper->console(['cache:pool:clear', 'cache.http', 'cache.object']);
        }

        $output->writeln('Shopware is installed, running update tools');

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

        $this->pluginHelper->installPlugins($configuration->skipAssetsInstall);
        $this->pluginHelper->updatePlugins($configuration->skipAssetsInstall);
        $this->pluginHelper->deactivatePlugins($configuration->skipAssetsInstall);
        $this->pluginHelper->removePlugins($configuration->skipAssetsInstall);

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

        $this->oneTimeTasks->execute($output);

        $this->hookExecutor->execute(HookExecutor::HOOK_POST_UPDATE);

        if ($this->configuration->maintenance->enabled) {
            $this->state->disableMaintenanceMode();

            $output->writeln('Maintenance mode is disabled, clearing cache to make sure the storefront is visible again');
            $this->processHelper->console(['cache:pool:clear', 'cache.http', 'cache.object']);
        }
    }
}
