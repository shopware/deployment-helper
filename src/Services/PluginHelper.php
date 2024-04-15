<?php declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Digilist\DependencyGraph\DependencyGraph;
use Digilist\DependencyGraph\DependencyNode;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\PhpSubprocess;

/**
 * @phpstan-type Plugin array{name: string, composerName: string, path: string, installedAt: string|null, version: string, upgradeVersion: string|null, active: boolean}
 */
readonly class PluginHelper
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private ProcessHelper $processHelper,
        private ProjectConfiguration $configuration,
    ) {}

    /**
     * @return array<Plugin>
     */
    public function all(): array
    {
        $text = (new PhpSubprocess(['bin/console', 'plugin:list', '--json'], $this->projectDir))->mustRun()->getOutput();

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
            $composerJson = Path::join($this->projectDir, $item['path'], 'composer.json');

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

    public function installPlugins(): void
    {
        foreach ($this->all() as $plugin) {
            if (!$this->configuration->isExtensionManaged($plugin['name'])) {
                continue;
            }

            if ($plugin['active']) {
                continue;
            }

            // plugin is installed, but not active
            if (null !== $plugin['installedAt']) {
                $this->processHelper->console(['plugin:activate', $plugin['name']]);

                continue;
            }

            $this->processHelper->console(['plugin:install', $plugin['name'], '--activate']);
        }
    }

    public function updatePlugins(): void
    {
        foreach ($this->all() as $plugin) {
            if (!$this->configuration->isExtensionManaged($plugin['name'])) {
                continue;
            }

            if (null === $plugin['upgradeVersion'] || $plugin['version'] === $plugin['upgradeVersion']) {
                continue;
            }

            $this->processHelper->console(['plugin:update', $plugin['name']]);
        }
    }
}
