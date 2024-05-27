<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Digilist\DependencyGraph\DependencyGraph;
use Digilist\DependencyGraph\DependencyNode;
use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

/**
 * @phpstan-type Plugin array{name: string, composerName: string, path: string, installedAt: string|null, version: string, upgradeVersion: string|null, active: boolean}
 */
class PluginLoader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly ProcessHelper $processHelper,
    ) {
    }

    /**
     * @return array<string, Plugin>
     */
    public function all(): array
    {
        $data = json_decode($this->processHelper->getPluginList(), true, 512, \JSON_THROW_ON_ERROR);

        $graph = new DependencyGraph();
        $byName = [];
        $nodes = [];

        foreach ($data as $item) {
            $byName[$item['name']] = $item;
            $nodes[$item['composerName']] = new DependencyNode($item['name']);
            $graph->addNode($nodes[$item['composerName']]);
        }

        foreach ($data as $item) {
            $composerJson = Path::join($this->projectDir, $item['path'], 'composer.json');

            if (file_exists($composerJson)) {
                $composer = json_decode((string) file_get_contents($composerJson), true, 512, \JSON_THROW_ON_ERROR);

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
        foreach ($graph->resolve() as $name) {
            $formatted[$name] = $byName[$name];
        }

        return $formatted;
    }
}
