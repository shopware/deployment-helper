<?php declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Composer\InstalledVersions;
use DOMNode;
use DOMNodeList;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-type App array{name: string, version: string}
 */
class AppLoader
{
    public function __construct(#[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir) {}

    /**
     * @return App[]
     */
    public function all(): array
    {
        $files = [... $this->loadFromFilesystem(), ...$this->loadFromComposer()];

        return $this->loadApps($files);
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
        /** @var DOMNodeList<DOMNode> $node */
        $node = $xpath->query($query);

        return $node->item(0)?->nodeValue;
    }
}
