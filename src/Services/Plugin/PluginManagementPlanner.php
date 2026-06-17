<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services\Plugin;

use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Struct\Command\ActivatePlugin;
use Shopware\Deployment\Struct\Command\ConsoleCommand;
use Shopware\Deployment\Struct\Command\DeactivatePlugin;
use Shopware\Deployment\Struct\Command\InstallPlugins;
use Shopware\Deployment\Struct\Command\UninstallPlugin;
use Shopware\Deployment\Struct\Command\UpdatePlugin;
use Shopware\Deployment\Struct\PluginCollection;

/**
 * Decides which console commands should be executed for the plugin lifecycle operations. The
 * planner is pure: it turns the loaded plugins and project configuration into an ordered list of
 * commands without performing any I/O, which keeps the decision logic easy to test in isolation.
 */
class PluginManagementPlanner
{
    public function __construct(
        private readonly ProjectConfiguration $configuration,
    ) {
    }

    /**
     * Plugins without dependencies on other plugins are collected and installed in a single
     * `plugin:install` call to avoid spawning one process per plugin. The batch is flushed before
     * any plugin that requires a standalone install (a dependency or an activation), which preserves
     * the topological install order produced by the loader.
     *
     * @param list<string> $additionalParameters
     *
     * @return list<ConsoleCommand>
     */
    public function planInstall(PluginCollection $plugins, array $additionalParameters): array
    {
        $commands = [];

        /** @var list<string> $batch */
        $batch = [];

        foreach ($plugins->all() as $plugin) {
            if (!$this->configuration->extensionManagement->canExtensionBeInstalled($plugin['name'])) {
                continue;
            }

            if ($plugin['active']) {
                continue;
            }

            // plugin is installed, but not active
            if ($plugin['installedAt'] !== null) {
                if ($this->configuration->extensionManagement->canExtensionBeActivated($plugin['name'])) {
                    $commands = $this->flushBatch($commands, $batch, $additionalParameters);
                    $batch = [];
                    $commands[] = new ActivatePlugin($plugin['name'], $additionalParameters);
                }

                continue;
            }

            $activate = $this->configuration->extensionManagement->canExtensionBeActivated($plugin['name']);

            if (!$activate && !$plugins->hasDependencies($plugin['name'])) {
                $batch[] = $plugin['name'];

                continue;
            }

            $commands = $this->flushBatch($commands, $batch, $additionalParameters);
            $batch = [];
            $commands[] = new InstallPlugins([$plugin['name']], activate: $activate, additionalParameters: $additionalParameters);
        }

        return $this->flushBatch($commands, $batch, $additionalParameters);
    }

    /**
     * @param list<string> $additionalParameters
     *
     * @return list<ConsoleCommand>
     */
    public function planUpdate(PluginCollection $plugins, array $additionalParameters): array
    {
        $commands = [];

        foreach ($plugins->all() as $plugin) {
            if (!$this->configuration->extensionManagement->canExtensionBeInstalled($plugin['name'])) {
                continue;
            }

            if ($this->configuration->extensionManagement->shouldExtensionBeForceUpdated($plugin['name'])) {
                $commands[] = new UpdatePlugin($plugin['name'], $additionalParameters);

                continue;
            }

            if ($plugin['upgradeVersion'] === null || $plugin['version'] === $plugin['upgradeVersion']) {
                continue;
            }

            $commands[] = new UpdatePlugin($plugin['name'], $additionalParameters);
        }

        return $commands;
    }

    /**
     * @param list<string> $additionalParameters
     *
     * @return list<ConsoleCommand>
     */
    public function planDeactivate(PluginCollection $plugins, array $additionalParameters): array
    {
        $commands = [];

        foreach ($plugins->all() as $plugin) {
            if ($plugin['installedAt'] === null || !$plugin['active'] || !$this->configuration->extensionManagement->canExtensionBeDeactivated($plugin['name'])) {
                continue;
            }

            $commands[] = new DeactivatePlugin($plugin['name'], $additionalParameters);
        }

        return $commands;
    }

    /**
     * @param list<string> $additionalParameters
     *
     * @return list<ConsoleCommand>
     */
    public function planRemove(PluginCollection $plugins, array $additionalParameters): array
    {
        $commands = [];

        foreach ($plugins->all() as $plugin) {
            if ($plugin['installedAt'] === null || !$this->configuration->extensionManagement->canExtensionBeRemoved($plugin['name'])) {
                continue;
            }

            $keepUserData = $this->configuration->extensionManagement->overrides[$plugin['name']]['keepUserData'] ?? false;

            $commands[] = new UninstallPlugin($plugin['name'], $keepUserData, $additionalParameters);
        }

        return $commands;
    }

    /**
     * Appends the batched dependency-free plugins as a single `plugin:install` command, if any are
     * pending. The caller is responsible for resetting the batch afterwards.
     *
     * @param list<ConsoleCommand> $commands
     * @param list<string>         $batch
     * @param list<string>         $additionalParameters
     *
     * @return list<ConsoleCommand>
     */
    private function flushBatch(array $commands, array $batch, array $additionalParameters): array
    {
        if ($batch !== []) {
            $commands[] = new InstallPlugins($batch, additionalParameters: $additionalParameters);
        }

        return $commands;
    }
}
