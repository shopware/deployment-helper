<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Output\OutputInterface;

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

        if ($this->state->getPreviousVersion() !== $this->state->getCurrentVersion()) {
            $output->writeln(\sprintf('Updating Shopware from %s to %s', $this->state->getPreviousVersion(), $this->state->getCurrentVersion()));

            $additionalUpdateParameters = [];

            if ($configuration->skipAssetsInstall) {
                $additionalUpdateParameters[] = '--skip-asset-build';
            }

            $this->processHelper->console(['system:update:finish', ...$additionalUpdateParameters]);
            $this->state->setVersion($this->state->getCurrentVersion());
        }

        $salesChannelUrl = EnvironmentHelper::getVariable('SALES_CHANNEL_URL');

        if ($salesChannelUrl !== null && $this->state->isStorefrontInstalled() && !$this->state->isSalesChannelExisting($salesChannelUrl)) {
            $this->processHelper->console(['sales-channel:create:storefront', '--name=Storefront', '--url=' . $salesChannelUrl]);
        }

        $this->processHelper->console(['plugin:refresh']);
        $this->processHelper->console(['theme:refresh']);
        $this->processHelper->console(['scheduled-task:register']);

        $this->pluginHelper->installPlugins($configuration->skipAssetsInstall);
        $this->pluginHelper->updatePlugins($configuration->skipAssetsInstall);
        $this->appHelper->installApps();
        $this->appHelper->updateApps();

        if (!$configuration->skipThemeCompile) {
            $this->processHelper->console(['theme:compile', '--active-only']);
        }

        $this->oneTimeTasks->execute($output);

        $this->hookExecutor->execute(HookExecutor::HOOK_POST_UPDATE);

        if ($this->configuration->maintenance->enabled) {
            $this->state->disableMaintenanceMode();

            $output->writeln('Disabling maintenance mode, clearing cache to make sure it is visible');
            $this->processHelper->console(['cache:pool:clear', 'cache.http', 'cache.object']);
        }
    }
}
