<?php declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\PluginHelper;
use Shopware\Deployment\Services\PluginLoader;

#[CoversClass(PluginHelper::class)]
class PluginHelperTest extends TestCase
{
    public function testInstallSkipped(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->enabled = false;

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects(static::never())->method('console');

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->installPlugins();
    }

    public function testInstallActiveSkipped(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects(static::never())->method('console');

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->installPlugins();
    }

    public function testInstallNotInstalled(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects(static::once())->method('console')->with(['plugin:install', 'TestPlugin', '--activate']);

        $helper = new PluginHelper(
            $this->getPluginLoader(active:false, installedAt: null),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->installPlugins();
    }

    public function testInstallNotInstalledSkipAssets(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects(static::once())->method('console')->with(['plugin:install', 'TestPlugin', '--activate', '--skip-asset-build']);

        $helper = new PluginHelper(
            $this->getPluginLoader(active:false, installedAt: null),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->installPlugins(true);
    }

    public function testInstalledButNotActive(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects(static::once())->method('console')->with(['plugin:activate', 'TestPlugin']);

        $helper = new PluginHelper(
            $this->getPluginLoader(active:false),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->installPlugins();
    }

    public function testUpdateSkipped(): void
    {
        $configuration = new ProjectConfiguration();
        $configuration->extensionManagement->enabled = false;

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects(static::never())->method('console');

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            $configuration,
        );

        $helper->updatePlugins();
    }

    public function testUpdateNoUpgrade(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects(static::never())->method('console');

        $helper = new PluginHelper(
            $this->getPluginLoader(),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->updatePlugins();
    }

    public function testUpdate(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects(static::once())->method('console')->with(['plugin:update', 'TestPlugin']);

        $helper = new PluginHelper(
            $this->getPluginLoader(upgradeVersion: '1.0.1'),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->updatePlugins();
    }

    public function testUpdateDisableAssetBuild(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->expects(static::once())->method('console')->with(['plugin:update', 'TestPlugin', '--skip-asset-build']);

        $helper = new PluginHelper(
            $this->getPluginLoader(upgradeVersion: '1.0.1'),
            $processHelper,
            new ProjectConfiguration(),
        );

        $helper->updatePlugins(true);
    }

    public function getPluginLoader(bool $active = true, ?string $installedAt = 'test', ?string $upgradeVersion = null): PluginLoader&MockObject
    {
        $loader = $this->createMock(PluginLoader::class);

        $loader->method('all')->willReturn([
            [
                'name' => 'TestPlugin',
                'composerName' => 'test/test-plugin',
                'path' => '/var/www/html/custom/plugins/TestPlugin',
                'installedAt' => $installedAt,
                'version' => '1.0.0',
                'upgradeVersion' => $upgradeVersion,
                'active' => $active,
            ],
        ]);

        return $loader;
    }
}
