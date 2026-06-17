<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\Plugin\PluginLoader;
use Shopware\Deployment\Struct\PluginCollection;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

#[CoversClass(PluginLoader::class)]
#[CoversClass(PluginCollection::class)]
class PluginLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = Path::join(sys_get_temp_dir(), uniqid('plugin-loader-', true));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testLoad(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $processHelper
            ->method('getPluginList')
            ->willReturn('[{"name":"TestPlugin", "version": "1.0.0", "composerName": "test/test-plugin", "path": "custom/plugins/TestPlugin", "active": true, "installedAt": "2021-01-01 00:00:00", "upgradeVersion": "1.0.1"}]');

        $plugins = (new PluginLoader($this->tempDir, $processHelper))->all(new BufferedOutput());

        static::assertCount(1, $plugins);
        static::assertArrayHasKey('TestPlugin', $plugins);
    }

    public function testLoadWithInvalidPluginJson(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $processHelper
            ->method('getPluginList')
            ->willReturn('This is not a JSON string');

        $output = new BufferedOutput();
        $plugins = (new PluginLoader($this->tempDir, $processHelper))->all($output);
        self::assertSame([], $plugins);

        $fetchedOutput = $output->fetch();
        self::assertStringContainsString('Unable to parse plugin list. Error: Syntax error', $fetchedOutput);
        self::assertStringContainsString('Invalid JSON string: This is not a JSON string', $fetchedOutput);
    }

    public function testLoadDependenciesInRightOrder(): void
    {
        $fixture = (new PluginFixture())
            ->plugin('Plugin1', 'plugin/1', requires: ['plugin/2'])
            ->plugin('Plugin2', 'plugin/2')
            ->write($this->tempDir);

        $plugins = $this->load($fixture)->all(new BufferedOutput());

        static::assertCount(2, $plugins);
        static::assertSame(
            [
                'Plugin2' => $fixture->entry('Plugin2'),
                'Plugin1' => $fixture->entry('Plugin1'),
            ],
            $plugins,
        );
    }

    public function testLoadSeparatesPluginsWithAndWithoutDependencies(): void
    {
        $fixture = (new PluginFixture())
            ->plugin('Plugin1', 'plugin/1', requires: ['plugin/2'])
            ->plugin('Plugin2', 'plugin/2')
            ->write($this->tempDir);

        $plugins = $this->load($fixture)->load(new BufferedOutput());

        static::assertTrue($plugins->hasDependencies('Plugin1'));
        static::assertFalse($plugins->hasDependencies('Plugin2'));
    }

    public function testLoadDependenciesInRightOrderWithReplaces(): void
    {
        // Plugin1's composer.json requires plugin/2, but plugin:list reports the store package
        // names. The composer.lock replace entries map the requires back onto those names.
        $fixture = (new PluginFixture())
            ->plugin('Plugin1', 'store.shopware.com/plugin1', requires: ['plugin/2'], composerJsonName: 'plugin/1')
            ->plugin('Plugin2', 'store.shopware.com/plugin2', composerJsonName: 'plugin/2')
            ->lockReplace('store.shopware.com/plugin1', ['plugin/1'])
            ->lockReplace('store.shopware.com/plugin2', ['plugin/2'])
            ->write($this->tempDir);

        $plugins = $this->load($fixture)->all(new BufferedOutput());

        static::assertCount(2, $plugins);
        static::assertSame(
            [
                'Plugin2' => $fixture->entry('Plugin2'),
                'Plugin1' => $fixture->entry('Plugin1'),
            ],
            $plugins,
        );
    }

    private function load(PluginFixture $fixture): PluginLoader
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper->method('getPluginList')->willReturn($fixture->pluginListJson());

        return new PluginLoader($fixture->dir(), $processHelper);
    }
}
