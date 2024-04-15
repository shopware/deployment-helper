<?php

namespace Shopware\Deployment\Services;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-type App array{name: string, version: string}
 */
readonly class AppHelper
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private ProcessHelper $processHelper,
        private Connection $connection,
        private ProjectConfiguration $configuration,
    ) {}

    /**
     * @return App[]
     */
    public function all(): array
    {
        $files = [... $this->loadFromFilesystem(), ...$this->loadFromComposer()];

        return $this->loadApps($files);
    }

    /**
     * Install all apps that are not installed.
     */
    public function installApps(): void
    {
        $installed = $this->connection->fetchAllAssociativeIndexed('SELECT name, version, active FROM app');

        foreach ($this->all() as $app) {
            if (!$this->configuration->isExtensionManaged($app['name'])) {
                continue;
            }

            if (isset($installed[$app['name']])) {
                if (!$installed[$app['name']]['active']) {
                    $this->processHelper->console(['app:update', $app['name']]);
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

        foreach ($this->all() as $app) {
            if (!$this->configuration->isExtensionManaged($app['name'])) {
                continue;
            }

            if (!isset($installed[$app['name']])) {
                continue;
            }

            if (version_compare($installed[$app['name']]['version'], $app['version'], '>=')) {
                continue;
            }

            $this->processHelper->console(['app:update', $app['name']]);
        }
    }

    /**
     * @return array<string>
     */
    private function loadFromFilesystem(): array
    {
        $appDir = $this->projectDir . '/custom/apps';
        if (!file_exists($appDir)) {
            return [];
        }

        $files = [];
        $finder = new Finder();
        $finder->in($appDir)
            ->depth('<= 1')
            ->name('manifest.xml')
        ;

        foreach ($finder->files() as $xml) {
            $files[] = $xml->getPathname();
        }

        return $files;
    }

    /**
     * @return array<string>
     */
    private function loadFromComposer(): array
    {
        $files = [];

        foreach (InstalledVersions::getInstalledPackagesByType('shopware-app') as $packageName) {
            $path = InstalledVersions::getInstallPath($packageName);

            $files[] = $path . '/manifest.xml';
        }

        return $files;
    }

    /**
     * @param array<string> $files
     *
     * @return App[]
     */
    private function loadApps(array $files): array
    {
        $apps = [];

        foreach ($files as $file) {
            $appXml = XmlUtils::loadFile($file);

            $xpath =  new \DOMXPath($appXml);

            $name = $this->getNodeValueByPath($xpath, '/manifest/meta/name');
            $version = $this->getNodeValueByPath($xpath, '/manifest/meta/version');

            if ($name === null || $version === null) {
                continue;
            }

            $apps[] = [
                'name' => $name,
                'version' => $version,
            ];
        }

        return $apps;
    }

    private function getNodeValueByPath(\DOMXPath $xpath, string $query): ?string
    {
        $node = $xpath->query($query);

        if (! $node instanceof \DOMNodeList) {
            return null;
        }

        return $node->item(0)?->nodeValue;
    }
}
