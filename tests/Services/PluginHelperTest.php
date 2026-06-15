<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Config\ProjectExtensionManagement;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\PluginHelper;
use Shopware\Deployment\Services\PluginLoader;
use Shopware\Deployment\Struct\PluginCollection;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(PluginHelper::class)]
#[CoversClass(PluginCollection::class)]
class PluginHelperTest extends TestCase
{
    public function testInstallSkipped(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->enabled = false;

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->never())->method('console');

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->installPlugins(new BufferedOutput());
    }

    public function testInstallActiveSkipped(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->never())->method('console');

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->installPlugins(new BufferedOutput());
    }

    public function testInstallNotInstalled(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:install', 'TestPlugin', '--activate']);

        $helper = new PluginHelper(
            $this->getPluginLoader(active: false, installedAt: null),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->installPlugins(new BufferedOutput());
    }

    public function testInstallNotInstalledNotActive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:install', 'TestPlugin']);

        $helper = new PluginHelper(
            $this->getPluginLoader(active: false, installedAt: null),
            $processHelper,
            $configuration,
        );

        $helper->installPlugins(new BufferedOutput());
    }

    public function testInstallNotInstalledSkipAssets(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:install', 'TestPlugin', '--activate', '--skip-asset-build']);

        $helper = new PluginHelper(
            $this->getPluginLoader(active: false, installedAt: null),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->installPlugins(new BufferedOutput(), true);
    }

    public function testInstalledButNotActive(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:activate', 'TestPlugin']);

        $helper = new PluginHelper(
            $this->getPluginLoader(active: false),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->installPlugins(new BufferedOutput());
    }

    public function testInstallMultipleNotInstalledWithoutDependenciesNotActive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];
        $configuration->extensionManagement->overrides['OtherPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:install', 'TestPlugin', 'OtherPlugin']);

        $helper = new PluginHelper(
            $this->getPluginLoaderWithPlugins([
                $this->getPlugin(active: false, installedAt: null),
                $this->getPlugin(name: 'OtherPlugin', active: false, installedAt: null),
            ]),
            $processHelper,
            $configuration,
        );

        $helper->installPlugins(new BufferedOutput());
    }

    public function testInstallNotInstalledWithDependenciesNotActive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];
        $configuration->extensionManagement->overrides['OtherPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->exactly(2))->method('console')->willReturnCallback(static function (array $command): void {
            static $commands = [
                ['plugin:install', 'TestPlugin'],
                ['plugin:install', 'OtherPlugin'],
            ];

            self::assertSame(array_shift($commands), $command);
        });

        $helper = new PluginHelper(
            $this->getPluginLoaderWithPlugins(
                [
                    $this->getPlugin(active: false, installedAt: null),
                    $this->getPlugin(name: 'OtherPlugin', active: false, installedAt: null),
                ],
                ['OtherPlugin'],
            ),
            $processHelper,
            $configuration,
        );

        $helper->installPlugins(new BufferedOutput());
    }

    public function testUpdateSkipped(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->enabled = false;

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->never())->method('console');

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->updatePlugins(new BufferedOutput());
    }

    public function testUpdateNoUpgrade(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->never())->method('console');

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->updatePlugins(new BufferedOutput());
    }

    public function testUpdateWhenForcedButSameVersion(): void
    {
        $projectConfiguration = new ProjectConfiguration();
        $projectConfiguration->extensionManagement->forceUpdates = ['TestPlugin'];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:update', 'TestPlugin']);

        $helper = new PluginHelper(
            $this->getPluginLoader(upgradeVersion: '1.0.0'),
            $processHelper,
            $projectConfiguration,
        );

        $helper->updatePlugins(new BufferedOutput());
    }

    public function testNoUpdateWhenNotForcedWithSameVersion(): void
    {
        $projectConfiguration = new ProjectConfiguration();

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->never())->method('console');

        $helper = new PluginHelper(
            $this->getPluginLoader(upgradeVersion: '1.0.0'),
            $processHelper,
            $projectConfiguration,
        );

        $helper->updatePlugins(new BufferedOutput());
    }

    public function testUpdate(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:update', 'TestPlugin']);

        $helper = new PluginHelper(
            $this->getPluginLoader(upgradeVersion: '1.0.1'),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->updatePlugins(new BufferedOutput());
    }

    public function testUpdateInstalledNotActive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:update', 'TestPlugin']);

        $helper = new PluginHelper(
            $this->getPluginLoader(active: false, upgradeVersion: '1.0.1'),
            $processHelper,
            $configuration,
        );

        $helper->updatePlugins(new BufferedOutput());
    }

    public function testUpdateInstalledActive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:update', 'TestPlugin']);

        $helper = new PluginHelper(
            $this->getPluginLoader(upgradeVersion: '1.0.1'),
            $processHelper,
            $configuration,
        );

        $helper->updatePlugins(new BufferedOutput());
    }

    public function testUpdateDisableAssetBuild(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:update', 'TestPlugin', '--skip-asset-build']);

        $helper = new PluginHelper(
            $this->getPluginLoader(upgradeVersion: '1.0.1'),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->updatePlugins(new BufferedOutput(), true);
    }

    public function testInactive(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:deactivate', 'TestPlugin']);

        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INACTIVE];

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->deactivatePlugins(new BufferedOutput());
    }

    public function testInactiveWithDisabledAssets(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:deactivate', 'TestPlugin', '--skip-asset-build']);

        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INACTIVE];

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->deactivatePlugins(new BufferedOutput(), true);
    }

    public function testInactiveNotMatching(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->never())->method('console');

        $configuration = new ProjectConfiguration();

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->deactivatePlugins(new BufferedOutput());
    }

    public function testUninstall(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:uninstall', 'TestPlugin']);

        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_REMOVE];

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->removePlugins(new BufferedOutput());
    }

    public function testUninstallWithDisabledAssets(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:uninstall', 'TestPlugin', '--skip-asset-build']);

        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_REMOVE];

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->removePlugins(new BufferedOutput(), true);
    }

    public function testUninstallWithDisabledAssetsKeepData(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->once())->method('console')->with(['plugin:uninstall', 'TestPlugin', '--keep-user-data', '--skip-asset-build']);

        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_REMOVE, 'keepUserData' => true];

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->removePlugins(new BufferedOutput(), true);
    }

    public function testUninstallNotMatching(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects($this->never())->method('console');

        $configuration = new ProjectConfiguration();

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->removePlugins(new BufferedOutput(), true);
    }

    public function getPluginLoader(bool $active = true, ?string $installedAt = 'test', ?string $upgradeVersion = null): PluginLoader&MockObject
    {
        return $this->getPluginLoaderWithPlugins([$this->getPlugin(active: $active, installedAt: $installedAt, upgradeVersion: $upgradeVersion)]);
    }

    /**
     * @param list<array{name: string, composerName: string, path: string, installedAt: string|null, version: string, upgradeVersion: string|null, active: bool}> $plugins
     * @param list<string>                                                                                                                                        $pluginNamesWithDependencies
     */
    private function getPluginLoaderWithPlugins(array $plugins, array $pluginNamesWithDependencies = []): PluginLoader&MockObject
    {
        $loader = $this->createMock(PluginLoader::class);
        $pluginsByName = [];
        $pluginsWithDependencies = [];
        $pluginsWithoutDependencies = [];

        foreach ($plugins as $plugin) {
            $pluginsByName[$plugin['name']] = $plugin;

            if (\in_array($plugin['name'], $pluginNamesWithDependencies, true)) {
                $pluginsWithDependencies[$plugin['name']] = $plugin;

                continue;
            }

            $pluginsWithoutDependencies[$plugin['name']] = $plugin;
        }

        $collection = new PluginCollection($pluginsByName, $pluginsWithDependencies, $pluginsWithoutDependencies);

        $loader->method('all')->willReturn($pluginsByName);
        $loader->method('load')->willReturn($collection);

        return $loader;
    }

    /**
     * @return array{name: string, composerName: string, path: string, installedAt: string|null, version: string, upgradeVersion: string|null, active: bool}
     */
    private function getPlugin(string $name = 'TestPlugin', bool $active = true, ?string $installedAt = 'test', ?string $upgradeVersion = null): array
    {
        return [
            'name' => $name,
            'composerName' => 'test/' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name)),
            'path' => '/var/www/html/custom/plugins/' . $name,
            'installedAt' => $installedAt,
            'version' => '1.0.0',
            'upgradeVersion' => $upgradeVersion,
            'active' => $active,
        ];
    }
}
