<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Doctrine\DBAL\Connection;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Output\OutputInterface;

class InstallationManager
{
    public function __construct(
        private readonly ShopwareState $state,
        private readonly Connection $connection,
        private readonly ProcessHelper $processHelper,
        private readonly PluginHelper $pluginHelper,
        private readonly AppHelper $appHelper,
        private readonly HookExecutor $hookExecutor,
    ) {
    }

    public function run(RunConfiguration $configuration, OutputInterface $output): void
    {
        $this->processHelper->setTimeout($configuration->timeout);

        $output->writeln('Shopware is not installed, starting installation');

        $this->hookExecutor->execute(HookExecutor::HOOK_PRE_INSTALL);

        $shopLocale = EnvironmentHelper::getVariable('INSTALL_LOCALE', 'en-GB');
        $shopCurrency = EnvironmentHelper::getVariable('INSTALL_CURRENCY', 'EUR');
        $adminUser = EnvironmentHelper::getVariable('INSTALL_ADMIN_USERNAME', 'admin');
        $adminPassword = EnvironmentHelper::getVariable('INSTALL_ADMIN_PASSWORD', 'shopware');
        $appUrl = EnvironmentHelper::getVariable('APP_URL', 'http://localhost');
        $salesChannelUrl = EnvironmentHelper::getVariable('SALES_CHANNEL_URL', $appUrl);

        $additionalInstallParameters = [];

        if ($configuration->skipThemeCompile) {
            $additionalInstallParameters[] = '--no-assign-theme';
        }

        if ($configuration->skipAssetInstall) {
            $additionalInstallParameters[] = '--skip-assets-install';
        }

        $this->processHelper->console(['system:install', '--create-database', '--shop-locale=' . $shopLocale, '--shop-currency=' . $shopCurrency, '--force', ...$additionalInstallParameters]);
        $this->processHelper->console(['user:create', $adminUser, '--password=' . $adminPassword]);

        if ($this->state->isStorefrontInstalled()) {
            $this->removeExistingHeadlessSalesChannel();
            if (!$this->state->isSalesChannelExisting($salesChannelUrl)) {
                $this->processHelper->console(['sales-channel:create:storefront', '--name=Storefront', '--url=' . $salesChannelUrl]);
            }

            $themeChangeParameters = [];
            if ($configuration->skipThemeCompile) {
                $themeChangeParameters[] = '--no-compile';
            }

            $this->processHelper->console(['theme:change', '--all', 'Storefront', ...$themeChangeParameters]);

            if ($configuration->skipThemeCompile) {
                $this->processHelper->console(['theme:dump']);
            }
        }

        $this->state->disableFirstRunWizard();
        $this->state->setVersion($this->state->getCurrentVersion());

        $this->processHelper->console(['plugin:refresh']);
        $this->pluginHelper->installPlugins($configuration->skipAssetInstall);
        $this->pluginHelper->updatePlugins($configuration->skipAssetInstall);
        $this->appHelper->installApps();
        $this->appHelper->updateApps();

        $this->hookExecutor->execute(HookExecutor::HOOK_POST_INSTALL);
    }

    private function removeExistingHeadlessSalesChannel(): void
    {
        $this->connection->executeStatement('DELETE FROM sales_channel WHERE type_id = 0xf183ee5650cf4bdb8a774337575067a6');
    }
}
