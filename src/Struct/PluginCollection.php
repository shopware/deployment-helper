<?php

declare(strict_types=1);

namespace Shopware\Deployment\Struct;

use Shopware\Deployment\Services\Plugin\PluginLoader;

/**
 * @phpstan-import-type Plugin from PluginLoader
 */
class PluginCollection
{
    /**
     * @param array<string, Plugin> $plugins
     * @param array<string, true>   $pluginsWithDependencies
     */
    public function __construct(
        private readonly array $plugins,
        private readonly array $pluginsWithDependencies,
    ) {
    }

    /**
     * @return array<string, Plugin>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    public function hasDependencies(string $pluginName): bool
    {
        return \array_key_exists($pluginName, $this->pluginsWithDependencies);
    }
}
