<?php declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Doctrine\DBAL\Connection;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Helper\ProcessHelper;
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
    ) {}

    public function run(OutputInterface $output): void
    {
        $output->writeln('Shopware is not installed, starting installation');

        $this->hookExecutor->execute(HookExecutor::PRE_INSTALL);

        $shopLocale = EnvironmentHelper::getVariable('INSTALL_LOCALE', 'en-GB');
        $shopCurrency = EnvironmentHelper::getVariable('INSTALL_CURRENCY', 'EUR');
        $adminUser = EnvironmentHelper::getVariable('INSTALL_ADMIN_USERNAME', 'admin');
        $adminPassword = EnvironmentHelper::getVariable('INSTALL_ADMIN_PASSWORD', 'shopware');
        $appUrl = EnvironmentHelper::getVariable('APP_URL', 'http://localhost');

        $this->processHelper->console(['system:install', '--create-database', '--shop-locale=' . $shopLocale, '--shop-currency=' . $shopCurrency, '--force']);
        $this->processHelper->console(['user:create', (string) $adminUser, '--password=' . $adminPassword]);

        if ($this->state->isStorefrontInstalled()) {
            $this->removeExistingHeadlessSalesChannel();
            $this->processHelper->console(['sales-channel:create:storefront', '--name=Storefront', '--url=' . $appUrl]);
            $this->processHelper->console(['theme:change', '--all', 'Storefront']);
        }

        $this->state->setVersion($this->state->getCurrentVersion());

        $this->processHelper->console(['plugin:refresh']);
        $this->pluginHelper->installPlugins();
        $this->pluginHelper->updatePlugins();
        $this->appHelper->installApps();
        $this->appHelper->updateApps();

        $this->hookExecutor->execute(HookExecutor::POST_INSTALL);
    }

    private function removeExistingHeadlessSalesChannel(): void
    {
        $this->connection->executeStatement('DELETE FROM sales_channel WHERE type_id = 0xf183ee5650cf4bdb8a774337575067a6');
    }
}
