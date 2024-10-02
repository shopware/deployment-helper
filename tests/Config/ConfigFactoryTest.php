<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ConfigFactory;

#[CoversClass(ConfigFactory::class)]
class ConfigFactoryTest extends TestCase
{
    public function testCreateConfigWithoutFile(): void
    {
        $config = ConfigFactory::create(__DIR__);
        static::assertFalse($config->maintenance->enabled);
        static::assertTrue($config->extensionManagement->enabled);
        static::assertSame([], $config->extensionManagement->excluded);
        static::assertSame([], $config->oneTimeTasks);
        static::assertSame('', $config->hooks->pre);
    }

    public static function files(): \Generator
    {
        yield [__DIR__ . '/_fixtures/yml'];
        yield [__DIR__ . '/_fixtures/yaml'];
    }

    #[DataProvider('files')]
    public function testExistingConfigTest(string $configDir): void
    {
        $config = ConfigFactory::create($configDir);
        static::assertTrue($config->extensionManagement->enabled);
        static::assertSame(['Name'], $config->extensionManagement->excluded);
        static::assertSame(['foo' => 'test'], $config->oneTimeTasks);
        static::assertNotSame('', $config->hooks->pre);
        static::assertNotSame('', $config->hooks->post);
        static::assertNotSame('', $config->hooks->preInstall);
        static::assertNotSame('', $config->hooks->postInstall);
        static::assertNotSame('', $config->hooks->preUpdate);
        static::assertNotSame('', $config->hooks->postUpdate);
    }

    public function testExistingConfigWithMaintenance(): void
    {
        $config = ConfigFactory::create(__DIR__ . '/_fixtures/maintenance-mode');
        static::assertTrue($config->maintenance->enabled);
    }
}
