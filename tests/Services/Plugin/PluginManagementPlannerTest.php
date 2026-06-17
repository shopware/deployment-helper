<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Config\ProjectExtensionManagement;
use Shopware\Deployment\Services\Plugin\PluginManagementPlanner;
use Shopware\Deployment\Struct\Command\ActivatePlugin;
use Shopware\Deployment\Struct\Command\DeactivatePlugin;
use Shopware\Deployment\Struct\Command\InstallPlugins;
use Shopware\Deployment\Struct\Command\UninstallPlugin;
use Shopware\Deployment\Struct\Command\UpdatePlugin;
use Shopware\Deployment\Struct\PluginCollection;

#[CoversClass(PluginManagementPlanner::class)]
#[CoversClass(PluginCollection::class)]
#[CoversClass(InstallPlugins::class)]
#[CoversClass(ActivatePlugin::class)]
#[CoversClass(UpdatePlugin::class)]
#[CoversClass(DeactivatePlugin::class)]
#[CoversClass(UninstallPlugin::class)]
class PluginManagementPlannerTest extends TestCase
{
    public function testInstallSkipped(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->enabled = false;

        $commands = $this->planner($configuration)->planInstall($this->collection([$this->getPlugin()]), []);

        self::assertSame([], $commands);
    }

    public function testInstallActiveSkipped(): void
    {
        $commands = $this->planner()->planInstall($this->collection([$this->getPlugin()]), []);

        self::assertSame([], $commands);
    }

    public function testInstallNotInstalled(): void
    {
        $commands = $this->planner()->planInstall(
            $this->collection([$this->getPlugin(active: false, installedAt: null)]),
            [],
        );

        self::assertEquals([new InstallPlugins(['TestPlugin'], activate: true)], $commands);
    }

    public function testInstallNotInstalledNotActive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $commands = $this->planner($configuration)->planInstall(
            $this->collection([$this->getPlugin(active: false, installedAt: null)]),
            [],
        );

        self::assertEquals([new InstallPlugins(['TestPlugin'])], $commands);
    }

    public function testInstallNotInstalledSkipAssets(): void
    {
        $commands = $this->planner()->planInstall(
            $this->collection([$this->getPlugin(active: false, installedAt: null)]),
            ['--skip-asset-build'],
        );

        self::assertEquals([new InstallPlugins(['TestPlugin'], activate: true, additionalParameters: ['--skip-asset-build'])], $commands);
        self::assertSame(['plugin:install', 'TestPlugin', '--activate', '--skip-asset-build'], $commands[0]->toArgs());
    }

    public function testInstalledButNotActive(): void
    {
        $commands = $this->planner()->planInstall(
            $this->collection([$this->getPlugin(active: false)]),
            [],
        );

        self::assertEquals([new ActivatePlugin('TestPlugin')], $commands);
        self::assertSame(['plugin:activate', 'TestPlugin'], $commands[0]->toArgs());
    }

    public function testInstallMultipleNotInstalledWithoutDependenciesNotActive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];
        $configuration->extensionManagement->overrides['OtherPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $commands = $this->planner($configuration)->planInstall(
            $this->collection([
                $this->getPlugin(active: false, installedAt: null),
                $this->getPlugin(name: 'OtherPlugin', active: false, installedAt: null),
            ]),
            [],
        );

        self::assertEquals([new InstallPlugins(['TestPlugin', 'OtherPlugin'])], $commands);
    }

    public function testInstallNotInstalledWithDependenciesNotActive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];
        $configuration->extensionManagement->overrides['OtherPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $commands = $this->planner($configuration)->planInstall(
            $this->collection(
                [
                    $this->getPlugin(active: false, installedAt: null),
                    $this->getPlugin(name: 'OtherPlugin', active: false, installedAt: null),
                ],
                ['OtherPlugin'],
            ),
            [],
        );

        self::assertEquals([
            new InstallPlugins(['TestPlugin']),
            new InstallPlugins(['OtherPlugin']),
        ], $commands);
    }

    public function testInstallFlushesBatchBeforeAndAfterPluginWithDependencies(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['BatchA'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];
        $configuration->extensionManagement->overrides['DepPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];
        $configuration->extensionManagement->overrides['BatchB'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $commands = $this->planner($configuration)->planInstall(
            $this->collection(
                [
                    $this->getPlugin(name: 'BatchA', active: false, installedAt: null),
                    $this->getPlugin(name: 'DepPlugin', active: false, installedAt: null),
                    $this->getPlugin(name: 'BatchB', active: false, installedAt: null),
                ],
                ['DepPlugin'],
            ),
            [],
        );

        self::assertEquals([
            new InstallPlugins(['BatchA']),
            new InstallPlugins(['DepPlugin']),
            new InstallPlugins(['BatchB']),
        ], $commands);
    }

    public function testInstallFlushesBatchBeforeActivatedPlugin(): void
    {
        $configuration = new ProjectConfiguration();
        // BatchA is installable but not activatable, so it is batched. ActivePlugin keeps the
        // default state and therefore gets `--activate`, which forces the batch to be flushed first.
        $configuration->extensionManagement->overrides['BatchA'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $commands = $this->planner($configuration)->planInstall(
            $this->collection([
                $this->getPlugin(name: 'BatchA', active: false, installedAt: null),
                $this->getPlugin(name: 'ActivePlugin', active: false, installedAt: null),
            ]),
            [],
        );

        self::assertEquals([
            new InstallPlugins(['BatchA']),
            new InstallPlugins(['ActivePlugin'], activate: true),
        ], $commands);
    }

    public function testUpdateSkipped(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->enabled = false;

        $commands = $this->planner($configuration)->planUpdate($this->collection([$this->getPlugin()]), []);

        self::assertSame([], $commands);
    }

    public function testUpdateNoUpgrade(): void
    {
        $commands = $this->planner()->planUpdate($this->collection([$this->getPlugin()]), []);

        self::assertSame([], $commands);
    }

    public function testUpdateWhenForcedButSameVersion(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->forceUpdates = ['TestPlugin'];

        $commands = $this->planner($configuration)->planUpdate(
            $this->collection([$this->getPlugin(upgradeVersion: '1.0.0')]),
            [],
        );

        self::assertEquals([new UpdatePlugin('TestPlugin')], $commands);
        self::assertSame(['plugin:update', 'TestPlugin'], $commands[0]->toArgs());
    }

    public function testNoUpdateWhenNotForcedWithSameVersion(): void
    {
        $commands = $this->planner()->planUpdate(
            $this->collection([$this->getPlugin(upgradeVersion: '1.0.0')]),
            [],
        );

        self::assertSame([], $commands);
    }

    public function testUpdate(): void
    {
        $commands = $this->planner()->planUpdate(
            $this->collection([$this->getPlugin(upgradeVersion: '1.0.1')]),
            [],
        );

        self::assertEquals([new UpdatePlugin('TestPlugin')], $commands);
    }

    public function testUpdateInstalledNotActive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $commands = $this->planner($configuration)->planUpdate(
            $this->collection([$this->getPlugin(active: false, upgradeVersion: '1.0.1')]),
            [],
        );

        self::assertEquals([new UpdatePlugin('TestPlugin')], $commands);
    }

    public function testUpdateInstalledActive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $commands = $this->planner($configuration)->planUpdate(
            $this->collection([$this->getPlugin(upgradeVersion: '1.0.1')]),
            [],
        );

        self::assertEquals([new UpdatePlugin('TestPlugin')], $commands);
    }

    public function testUpdateDisableAssetBuild(): void
    {
        $commands = $this->planner()->planUpdate(
            $this->collection([$this->getPlugin(upgradeVersion: '1.0.1')]),
            ['--skip-asset-build'],
        );

        self::assertEquals([new UpdatePlugin('TestPlugin', ['--skip-asset-build'])], $commands);
    }

    public function testInactive(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INACTIVE];

        $commands = $this->planner($configuration)->planDeactivate($this->collection([$this->getPlugin()]), []);

        self::assertEquals([new DeactivatePlugin('TestPlugin')], $commands);
        self::assertSame(['plugin:deactivate', 'TestPlugin'], $commands[0]->toArgs());
    }

    public function testInactiveWithDisabledAssets(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INACTIVE];

        $commands = $this->planner($configuration)->planDeactivate(
            $this->collection([$this->getPlugin()]),
            ['--skip-asset-build'],
        );

        self::assertEquals([new DeactivatePlugin('TestPlugin', ['--skip-asset-build'])], $commands);
    }

    public function testInactiveNotMatching(): void
    {
        $commands = $this->planner()->planDeactivate($this->collection([$this->getPlugin()]), []);

        self::assertSame([], $commands);
    }

    public function testUninstall(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_REMOVE];

        $commands = $this->planner($configuration)->planUninstall($this->collection([$this->getPlugin()]), []);

        self::assertEquals([new UninstallPlugin('TestPlugin')], $commands);
    }

    public function testUninstallWithDisabledAssets(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_REMOVE];

        $commands = $this->planner($configuration)->planUninstall(
            $this->collection([$this->getPlugin()]),
            ['--skip-asset-build'],
        );

        self::assertEquals([new UninstallPlugin('TestPlugin', additionalParameters: ['--skip-asset-build'])], $commands);
    }

    public function testUninstallWithDisabledAssetsKeepData(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_REMOVE, 'keepUserData' => true];

        $commands = $this->planner($configuration)->planUninstall(
            $this->collection([$this->getPlugin()]),
            ['--skip-asset-build'],
        );

        self::assertEquals([new UninstallPlugin('TestPlugin', keepUserData: true, additionalParameters: ['--skip-asset-build'])], $commands);
        self::assertSame(['plugin:uninstall', 'TestPlugin', '--keep-user-data', '--skip-asset-build'], $commands[0]->toArgs());
    }

    public function testUninstallNotMatching(): void
    {
        $commands = $this->planner()->planUninstall($this->collection([$this->getPlugin()]), []);

        self::assertSame([], $commands);
    }

    private function planner(?ProjectConfiguration $configuration = null): PluginManagementPlanner
    {
        return new PluginManagementPlanner($configuration ?? new ProjectConfiguration());
    }

    /**
     * @param list<array{name: string, composerName: string, path: string, installedAt: string|null, version: string, upgradeVersion: string|null, active: bool}> $plugins
     * @param list<string>                                                                                                                                        $pluginNamesWithDependencies
     */
    private function collection(array $plugins, array $pluginNamesWithDependencies = []): PluginCollection
    {
        $pluginsByName = [];
        $pluginsWithDependencies = [];

        foreach ($plugins as $plugin) {
            $pluginsByName[$plugin['name']] = $plugin;

            if (\in_array($plugin['name'], $pluginNamesWithDependencies, true)) {
                $pluginsWithDependencies[$plugin['name']] = true;
            }
        }

        return new PluginCollection($pluginsByName, $pluginsWithDependencies);
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
