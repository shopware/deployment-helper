<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Digilist\DependencyGraph\DependencyGraph;
use Digilist\DependencyGraph\DependencyNode;
use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
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
    public function all(OutputInterface $output): array
    {
        $projectLockPath = Path::join($this->projectDir, 'composer.lock');
        $projectAliases = [];

        if (is_file($projectLockPath)) {
            $lockData = json_decode((string) file_get_contents($projectLockPath), true, flags: \JSON_THROW_ON_ERROR);

            foreach ($lockData['packages'] as $package) {
                if (isset($package['replace'])) {
                    foreach ($package['replace'] as $replace => $_) {
                        $projectAliases[$replace] = $package['name'];
                    }
                }
            }
        }

        $pluginJsonString = $this->processHelper->getPluginList();
        try {
            $data = json_decode($pluginJsonString, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $output->writeln('<error>Unable to parse plugin list. Error: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>Invalid JSON string: ' . $pluginJsonString . '</error>');

            return [];
        }

        $graph = new DependencyGraph();
        $byName = [];
        $nodes = [];

        foreach ($data as $item) {
            $byName[$item['name']] = $item;
            $nodes[$item['composerName']] = new DependencyNode($item['name'], $item['name']);
            $graph->addNode($nodes[$item['composerName']]);
        }

        foreach ($data as $item) {
            $composerJson = Path::join($this->projectDir, $item['path'], 'composer.json');

            if (is_file($composerJson)) {
                $composer = json_decode((string) file_get_contents($composerJson), true, flags: \JSON_THROW_ON_ERROR);

                if (isset($composer['require'])) {
                    foreach ($composer['require'] as $require => $version) {
                        if (isset($projectAliases[$require])) {
                            $require = $projectAliases[$require];
                        }

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
