<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct;

use Shopware\Deployment\Services\PluginLoader;

/**
 * @phpstan-import-type Plugin from PluginLoader
 */
class PluginCollection
{
    /**
     * @param array<string, Plugin> $plugins
     * @param array<string, Plugin> $pluginsWithDependencies
     * @param array<string, Plugin> $pluginsWithoutDependencies
     */
    public function __construct(
        private readonly array $plugins,
        private readonly array $pluginsWithDependencies,
        private readonly array $pluginsWithoutDependencies,
    ) {
    }

    /**
     * @return array<string, Plugin>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    /**
     * @return array<string, Plugin>
     */
    public function withDependencies(): array
    {
        return $this->pluginsWithDependencies;
    }

    /**
     * @return array<string, Plugin>
     */
    public function withoutDependencies(): array
    {
        return $this->pluginsWithoutDependencies;
    }

    public function hasDependencies(string $pluginName): bool
    {
        return \array_key_exists($pluginName, $this->pluginsWithDependencies);
    }
}
