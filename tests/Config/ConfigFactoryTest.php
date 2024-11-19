<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ConfigFactory;
use Shopware\Deployment\Config\ProjectExtensionManagement;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(ConfigFactory::class)]
class ConfigFactoryTest extends TestCase
{
    public function testCreateConfigWithoutFile(): void
    {
        $config = ConfigFactory::create(__DIR__);
        static::assertFalse($config->maintenance->enabled);
        static::assertTrue($config->extensionManagement->enabled);
        static::assertSame([], $config->extensionManagement->overrides);
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
        static::assertSame('ignore', $config->extensionManagement->overrides['Name']['state']);
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

    #[Env('SHOPWARE_STORE_LICENSE_DOMAIN', 'test')]
    public function testLicenseDomainPopulatedByEnv(): void
    {
        $config = ConfigFactory::create(__DIR__);
        static::assertSame('test', $config->store->licenseDomain);
    }

    public function testExistingConfigWithStoreConfig(): void
    {
        $config = ConfigFactory::create(__DIR__ . '/_fixtures/license-domain');
        static::assertSame('example.com', $config->store->licenseDomain);
    }

    public function testExistingConfigWithAlwaysClearCache(): void
    {
        $config = ConfigFactory::create(__DIR__ . '/_fixtures/always-clear-cache');
        static::assertTrue($config->alwaysClearCache);
    }

    public function testExistingConfigWithExtensionOverride(): void
    {
        $config = ConfigFactory::create(__DIR__ . '/_fixtures/extension-override');
        static::assertNotEmpty($config->extensionManagement->overrides);

        // Test FroshTest (without keepUserData)
        static::assertArrayHasKey('FroshTest', $config->extensionManagement->overrides);
        static::assertSame(ProjectExtensionManagement::LIFECYCLE_STATE_REMOVE, $config->extensionManagement->overrides['FroshTest']['state']);
        static::assertArrayHasKey('keepUserData', $config->extensionManagement->overrides['FroshTest']);

        // Test FroshTest2 (with keepUserData)
        static::assertArrayHasKey('FroshTest2', $config->extensionManagement->overrides);
        static::assertSame(ProjectExtensionManagement::LIFECYCLE_STATE_REMOVE, $config->extensionManagement->overrides['FroshTest2']['state']);
        static::assertArrayHasKey('keepUserData', $config->extensionManagement->overrides['FroshTest2']);
        static::assertTrue($config->extensionManagement->overrides['FroshTest2']['keepUserData']);
    }
}
