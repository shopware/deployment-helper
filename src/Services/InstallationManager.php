<?php declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Shopware\Deployment\Helper\EnvironmentHelper;
use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class InstallationManager
{
    public function __construct(
        private ShopwareState $state,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private Connection $connection,
    ) {}

    public function run(OutputInterface $output): void
    {
        $output->writeln('Shopware is not installed, starting installation');
        $shopLocale = EnvironmentHelper::getVariable('INSTALL_LOCALE', 'en-GB');
        $shopCurrency = EnvironmentHelper::getVariable('INSTALL_CURRENCY', 'EUR');
        $adminUser = EnvironmentHelper::getVariable('INSTALL_ADMIN_USERNAME', 'admin');
        $adminPassword = EnvironmentHelper::getVariable('INSTALL_ADMIN_PASSWORD', 'shopware');
        $appUrl = EnvironmentHelper::getVariable('APP_URL', 'http://localhost');

        ProcessHelper::console(['system:install', '--create-database', '--shop-locale=' . $shopLocale, '--shop-currency=' . $shopCurrency, '--force']);
        ProcessHelper::console(['user:create', (string) $adminUser, '--password=' . $adminPassword]);

        if (InstalledVersions::isInstalled('shopware/storefront')) {
            $this->removeExistingHeadlessSalesChannel();
            ProcessHelper::console(['sales-channel:create:storefront', '--name=Storefront', '--url=' . $appUrl]);
            ProcessHelper::console(['theme:change', '--all', 'Storefront']);
        }

        $this->state->setVersion($this->state->getCurrentVersion());

        ProcessHelper::console(['plugin:refresh']);
        PluginHelper::installPlugins($this->projectDir);
        PluginHelper::updatePlugins($this->projectDir);
    }

    private function removeExistingHeadlessSalesChannel(): void
    {
        $this->connection->executeStatement('DELETE FROM sales_channel WHERE type_id = 0xf183ee5650cf4bdb8a774337575067a6');
    }
}
