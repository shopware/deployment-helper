<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Doctrine\DBAL\Connection;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;

class AppHelper
{
    public function __construct(
        private readonly AppLoader $appLoader,
        private readonly ProcessHelper $processHelper,
        private readonly Connection $connection,
        private readonly ProjectConfiguration $configuration,
    ) {
    }

    /**
     * Install all apps that are not installed.
     */
    public function installApps(): void
    {
        $installed = $this->connection->fetchAllAssociativeIndexed('SELECT name, version, active FROM app');

        foreach ($this->appLoader->all() as $app) {
            if (!$this->configuration->isExtensionManaged($app['name'])) {
                continue;
            }

            if (isset($installed[$app['name']])) {
                if (!(bool) $installed[$app['name']]['active']) {
                    $this->processHelper->console(['app:activate', $app['name']]);
                }

                continue;
            }

            $this->processHelper->console(['app:install', $app['name'], '--activate', '--force']);
        }
    }

    /**
     * Install all apps that are not installed.
     */
    public function updateApps(): void
    {
        $installed = $this->connection->fetchAllAssociativeIndexed('SELECT name, version, active FROM app');

        foreach ($this->appLoader->all() as $app) {
            if (!$this->configuration->isExtensionManaged($app['name'])) {
                continue;
            }

            if (!isset($installed[$app['name']])) {
                continue;
            }

            if (version_compare($installed[$app['name']]['version'], $app['version'], '>=')) {
                continue;
            }

            $this->processHelper->console(['app:update', $app['name']]);
        }
    }
}
