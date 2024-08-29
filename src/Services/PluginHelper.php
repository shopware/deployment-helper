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
            if (!$this->configuration->isExtensionManaged($plugin['name'])) {
                continue;
            }

            if ($plugin['active']) {
                continue;
            }

            // plugin is installed, but not active
            if ($plugin['installedAt'] !== null) {
                $this->processHelper->console(['plugin:activate', $plugin['name'], ...$additionalParameters]);

                continue;
            }

            $this->processHelper->console(['plugin:install', $plugin['name'], '--activate', ...$additionalParameters]);
        }
    }

    public function updatePlugins(bool $skipAssetsInstall = false): void
    {
        $additionalParameters = [];

        if ($skipAssetsInstall) {
            $additionalParameters[] = '--skip-asset-build';
        }

        foreach ($this->pluginLoader->all() as $plugin) {
            if (!$this->configuration->isExtensionManaged($plugin['name'])) {
                continue;
            }

            if ($plugin['upgradeVersion'] === null || $plugin['version'] === $plugin['upgradeVersion']) {
                continue;
            }

            $this->processHelper->console(['plugin:update', $plugin['name'], ...$additionalParameters]);
        }
    }
}
