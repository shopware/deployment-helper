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
            if (!$this->configuration->extensionManagement->canExtensionBeInstalled($app['name'])) {
                continue;
            }

            if (isset($installed[$app['name']])) {
                if (!(bool) $installed[$app['name']]['active'] && $this->configuration->extensionManagement->canExtensionBeActivated($app['name'])) {
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

        $appNeedsToBeUpdated = false;

        foreach ($this->appLoader->all() as $app) {
            if (!$this->configuration->extensionManagement->canExtensionBeInstalled($app['name'])) {
                continue;
            }

            if (!isset($installed[$app['name']])) {
                continue;
            }

            if (version_compare($installed[$app['name']]['version'], $app['version'], '>=')) {
                continue;
            }

            $appNeedsToBeUpdated = true;
            break;
        }

        if ($appNeedsToBeUpdated) {
            $this->processHelper->console(['app:refresh', '--force']);
        }
    }

    public function deactivateApps(): void
    {
        $installed = $this->connection->fetchAllAssociative('SELECT name, version, active FROM app');

        foreach ($installed as $app) {
            if (!(bool) $app['active'] || !$this->configuration->extensionManagement->canExtensionBeDeactivated($app['name'])) {
                continue;
            }

            $this->processHelper->console(['app:deactivate', $app['name']]);
        }
    }

    public function removeApps(): void
    {
        $installed = $this->connection->fetchAllAssociative('SELECT name, version, active FROM app');

        foreach ($installed as $app) {
            if (!$this->configuration->extensionManagement->canExtensionBeRemoved($app['name'])) {
                continue;
            }

            $this->processHelper->console(['app:uninstall', $app['name']]);
        }
    }
}
