<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;

class PluginHelper
{
    public function __construct(
        private readonly PluginLoader $pluginLoader,
        private readonly ProcessHelper $processHelper,
        private readonly ProjectConfiguration $configuration,
    ) {
    }

    public function installPlugins(bool $skipAssetsInstall = false): void
    {
        $additionalParameters = [];

        if ($skipAssetsInstall) {
            $additionalParameters[] = '--skip-asset-build';
        }

        foreach ($this->pluginLoader->all() as $plugin) {
            if (!$this->configuration->extensionManagement->canExtensionBeInstalled($plugin['name'])) {
                continue;
            }

            if ($plugin['active']) {
                continue;
            }

            // plugin is installed, but not active
            if ($plugin['installedAt'] !== null) {
                if ($this->configuration->extensionManagement->canExtensionBeActivated($plugin['name'])) {
                    $this->processHelper->console(['plugin:activate', $plugin['name'], ...$additionalParameters]);
                }

                continue;
            }

            $activate = [];

            if ($this->configuration->extensionManagement->canExtensionBeActivated($plugin['name'])) {
                $activate[] = '--activate';
            }

            $this->processHelper->console(['plugin:install', $plugin['name'], ...$activate, ...$additionalParameters]);
        }
    }

    public function updatePlugins(bool $skipAssetsInstall = false): void
    {
        $additionalParameters = [];

        if ($skipAssetsInstall) {
            $additionalParameters[] = '--skip-asset-build';
        }

        foreach ($this->pluginLoader->all() as $plugin) {
            if (!$this->configuration->extensionManagement->canExtensionBeInstalled($plugin['name'])) {
                continue;
            }

            if ($this->configuration->extensionManagement->shouldExtensionBeForceUpdated($plugin['name'])) {
                $this->processHelper->console(['plugin:update', $plugin['name'], ...$additionalParameters]);
                continue;
            }

            if ($plugin['upgradeVersion'] === null || $plugin['version'] === $plugin['upgradeVersion']) {
                continue;
            }

            $this->processHelper->console(['plugin:update', $plugin['name'], ...$additionalParameters]);
        }
    }

    public function deactivatePlugins(bool $skipAssetsInstall = false): void
    {
        $additionalParameters = [];

        if ($skipAssetsInstall) {
            $additionalParameters[] = '--skip-asset-build';
        }

        foreach ($this->pluginLoader->all() as $plugin) {
            if ($plugin['installedAt'] === null || !$plugin['active'] || !$this->configuration->extensionManagement->canExtensionBeDeactivated($plugin['name'])) {
                continue;
            }

            $this->processHelper->console(['plugin:deactivate', $plugin['name'], ...$additionalParameters]);
        }
    }

    public function removePlugins(bool $skipAssetsInstall = false): void
    {
        $additionalParameters = [];

        if ($skipAssetsInstall) {
            $additionalParameters[] = '--skip-asset-build';
        }

        foreach ($this->pluginLoader->all() as $plugin) {
            if ($plugin['installedAt'] === null || !$this->configuration->extensionManagement->canExtensionBeRemoved($plugin['name'])) {
                continue;
            }

            $keepUserData = $this->configuration->extensionManagement->overrides[$plugin['name']]['keepUserData'] ?? false;

            $uninstallParameters = [];

            if ($keepUserData) {
                $uninstallParameters[] = '--keep-user-data';
            }

            $this->processHelper->console(['plugin:uninstall', $plugin['name'], ...$uninstallParameters, ...$additionalParameters]);
        }
    }
}
