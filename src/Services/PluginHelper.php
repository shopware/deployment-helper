<?php declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Digilist\DependencyGraph\DependencyGraph;
use Digilist\DependencyGraph\DependencyNode;
use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\PhpSubprocess;

/**
 * @phpstan-type Plugin array{name: string, composerName: string, path: string, installedAt: string|null, version: string, upgradeVersion: string|null, active: boolean}
 */
readonly class PluginHelper
{
    /**
     * @return array<Plugin>
     */
    public static function all(string $path): array
    {
        $text = (new PhpSubprocess(['bin/console', 'plugin:list', '--json']))->mustRun()->getOutput();

        /** @var Plugin[] $data */
        $data = json_decode($text, true, JSON_THROW_ON_ERROR);

        $graph = new DependencyGraph();
        $byName = [];

        foreach ($data as $item) {
            $byName[$item['name']] = $item;
            $nodes[$item['composerName']] = new DependencyNode($item['name']);
            $graph->addNode($nodes[$item['composerName']]);
        }

        foreach ($data as $item) {
            $composerJson = Path::join($path, $item['path'], 'composer.json');

            if (file_exists($composerJson)) {
                $composer = json_decode((string) file_get_contents($composerJson), true, 512, JSON_THROW_ON_ERROR);

                if (isset($composer['require'])) {
                    foreach ($composer['require'] as $require => $version) {
                        if (isset($nodes[$require])) {
                            $graph->addDependency($nodes[$item['composerName']], $nodes[$require]);
                        }
                    }
                }
            }
        }

        $formatted = [];

        /** @var string[] $resolved */
        $resolved = $graph->resolve();
        foreach ($resolved as $name) {
            $formatted[$name] = $byName[$name];
        }

        return $formatted;
    }

    public static function installPlugins(string $path): void
    {
        $plugins = self::all($path);

        foreach ($plugins as $plugin) {
            if ($plugin['active']) {
                continue;
            }

            // plugin is installed, but not active
            if (null !== $plugin['installedAt']) {
                ProcessHelper::console(['plugin:activate', $plugin['name']]);

                continue;
            }

            ProcessHelper::console(['plugin:install', $plugin['name'], '--activate']);
        }
    }

    public static function updatePlugins(string $path): void
    {
        $plugins = self::all($path);

        foreach ($plugins as $plugin) {
            if (null === $plugin['upgradeVersion'] || $plugin['version'] === $plugin['upgradeVersion']) {
                continue;
            }

            ProcessHelper::console(['plugin:update', $plugin['name']]);
        }
    }
}
