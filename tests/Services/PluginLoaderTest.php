<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\PluginLoader;
use Shopware\Deployment\Struct\PluginCollection;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(PluginLoader::class)]
#[CoversClass(PluginCollection::class)]
class PluginLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $processHelper
            ->method('getPluginList')
            ->willReturn('[{"name":"TestPlugin", "version": "1.0.0", "composerName": "test/test-plugin", "path": "custom/plugins/TestPlugin", "active": true, "installedAt": "2021-01-01 00:00:00", "upgradeVersion": "1.0.1"}]');

        $plugins = (new PluginLoader(__DIR__, $processHelper))->all(new BufferedOutput());

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
        $plugins = (new PluginLoader(__DIR__, $processHelper))->all($output);
        self::assertSame([], $plugins);

        $fetchedOutput = $output->fetch();
        self::assertStringContainsString('Unable to parse plugin list. Error: Syntax error', $fetchedOutput);
        self::assertStringContainsString('Invalid JSON string: This is not a JSON string', $fetchedOutput);
    }

    public function testLoadDependenciesInRightOrder(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $data = [
            [
                'name' => 'Plugin1',
                'composerName' => 'plugin/1',
                'path' => '_fixtures/plugin1',
                'version' => '1.0.0',
            ],
            [
                'name' => 'Plugin2',
                'composerName' => 'plugin/2',
                'path' => '_fixtures/plugin2',
                'version' => '1.0.0',
            ],
        ];

        $processHelper
            ->method('getPluginList')
            ->willReturn(json_encode($data, \JSON_THROW_ON_ERROR));

        $plugins = (new PluginLoader(__DIR__, $processHelper))->all(new BufferedOutput());

        static::assertCount(2, $plugins);
        static::assertSame(
            [
                'Plugin2' => [
                    'name' => 'Plugin2',
                    'composerName' => 'plugin/2',
                    'path' => '_fixtures/plugin2',
                    'version' => '1.0.0',
                ],
                'Plugin1' => [
                    'name' => 'Plugin1',
                    'composerName' => 'plugin/1',
                    'path' => '_fixtures/plugin1',
                    'version' => '1.0.0',
                ],
            ],
            $plugins,
        );
    }

    public function testLoadSeparatesPluginsWithAndWithoutDependencies(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $data = [
            [
                'name' => 'Plugin1',
                'composerName' => 'plugin/1',
                'path' => '_fixtures/plugin1',
                'version' => '1.0.0',
            ],
            [
                'name' => 'Plugin2',
                'composerName' => 'plugin/2',
                'path' => '_fixtures/plugin2',
                'version' => '1.0.0',
            ],
        ];

        $processHelper
            ->method('getPluginList')
            ->willReturn(json_encode($data, \JSON_THROW_ON_ERROR));

        $plugins = (new PluginLoader(__DIR__, $processHelper))->load(new BufferedOutput());

        static::assertSame(['Plugin1'], array_keys($plugins->withDependencies()));
        static::assertSame(['Plugin2'], array_keys($plugins->withoutDependencies()));
        static::assertTrue($plugins->hasDependencies('Plugin1'));
        static::assertFalse($plugins->hasDependencies('Plugin2'));
    }

    public function testLoadDependenciesInRightOrderWithReplaces(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $data = [
            [
                'name' => 'Plugin1',
                'composerName' => 'store.shopware.com/plugin1',
                'path' => 'plugin1',
                'version' => '1.0.0',
            ],
            [
                'name' => 'Plugin2',
                'composerName' => 'store.shopware.com/plugin2',
                'path' => 'plugin2',
                'version' => '1.0.0',
            ],
        ];

        $processHelper
            ->method('getPluginList')
            ->willReturn(json_encode($data, \JSON_THROW_ON_ERROR));

        $plugins = (new PluginLoader(__DIR__ . '/_fixtures', $processHelper))->all(new BufferedOutput());

        static::assertCount(2, $plugins);
        static::assertSame(
            [
                'Plugin2' => [
                    'name' => 'Plugin2',
                    'composerName' => 'store.shopware.com/plugin2',
                    'path' => 'plugin2',
                    'version' => '1.0.0',
                ],
                'Plugin1' => [
                    'name' => 'Plugin1',
                    'composerName' => 'store.shopware.com/plugin1',
                    'path' => 'plugin1',
                    'version' => '1.0.0',
                ],
            ],
            $plugins,
        );
    }
}
