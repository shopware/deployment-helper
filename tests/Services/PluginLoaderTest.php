<?php declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\PluginLoader;
use PHPUnit\Framework\TestCase;

class PluginLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);

        $processHelper
            ->method('getPluginList')
            ->willReturn('[{"name":"TestPlugin", "version": "1.0.0", "composerName": "test/test-plugin", "path": "custom/plugins/TestPlugin", "active": true, "installedAt": "2021-01-01 00:00:00", "upgradeVersion": "1.0.1"}]');

        $loader = new PluginLoader(__DIR__, $processHelper);
        $plugins = $loader->all();

        $this->assertCount(1, $plugins);
        $this->assertArrayHasKey('TestPlugin', $plugins);
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
            ->willReturn(json_encode($data, JSON_THROW_ON_ERROR));

        $loader = new PluginLoader(__DIR__, $processHelper);

        $plugins = $loader->all();

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
}
