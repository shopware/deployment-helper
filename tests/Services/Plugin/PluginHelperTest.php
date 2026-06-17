<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Config\ProjectExtensionManagement;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\Plugin\PluginHelper;
use Shopware\Deployment\Services\Plugin\PluginLoader;
use Shopware\Deployment\Services\Plugin\PluginManagementPlanner;
use Shopware\Deployment\Struct\PluginCollection;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(PluginHelper::class)]
class PluginHelperTest extends TestCase
{
    public function testInstallExecutesPlannedCommandsInOrder(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['BatchA'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];
        $configuration->extensionManagement->overrides['DepPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INSTALLED];

        $commands = $this->capture(
            static fn (PluginHelper $helper, BufferedOutput $output) => $helper->installPlugins($output),
            [
                $this->getPlugin(name: 'BatchA', active: false, installedAt: null),
                $this->getPlugin(name: 'DepPlugin', active: false, installedAt: null),
            ],
            ['DepPlugin'],
            $configuration,
        );

        self::assertSame([
            ['plugin:install', 'BatchA'],
            ['plugin:install', 'DepPlugin'],
        ], $commands);
    }

    public function testRemovePassesAdditionalParametersThrough(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->overrides['TestPlugin'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_REMOVE, 'keepUserData' => true];

        $commands = $this->capture(
            static fn (PluginHelper $helper, BufferedOutput $output) => $helper->removePlugins($output, true),
            [$this->getPlugin()],
            [],
            $configuration,
        );

        self::assertSame([['plugin:uninstall', 'TestPlugin', '--keep-user-data', '--skip-asset-build']], $commands);
    }

    public function testNothingToDoExecutesNoCommands(): void
    {
        $commands = $this->capture(
            static fn (PluginHelper $helper, BufferedOutput $output) => $helper->installPlugins($output),
            [$this->getPlugin()],
            [],
            new ProjectConfiguration(),
        );

        self::assertSame([], $commands);
    }

    /**
     * Runs the given action against a real planner and returns the console commands the helper
     * executed, in order.
     *
     * @param callable(PluginHelper, BufferedOutput): void                                                                                                        $action
     * @param list<array{name: string, composerName: string, path: string, installedAt: string|null, version: string, upgradeVersion: string|null, active: bool}> $plugins
     * @param list<string>                                                                                                                                        $pluginNamesWithDependencies
     *
     * @return list<list<string>>
     */
    private function capture(callable $action, array $plugins, array $pluginNamesWithDependencies, ProjectConfiguration $configuration): array
    {
        /** @var list<list<string>> $captured */
        $captured = [];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->method('console')->willReturnCallback(static function (array $command) use (&$captured): void {
            $captured[] = array_values(array_map(strval(...), $command));
        });

        $loader = $this->createMock(PluginLoader::class);
        $loader->method('load')->willReturn($this->collection($plugins, $pluginNamesWithDependencies));

        $helper = new PluginHelper($loader, $processHelper, new PluginManagementPlanner($configuration));

        $action($helper, new BufferedOutput());

        return $captured;
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
