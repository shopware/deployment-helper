<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Application;
use Shopware\Deployment\Config\ConfigFactory;
use Shopware\Deployment\Config\ProjectExtensionManagement;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(ConfigFactory::class)]
class ConfigFactoryTest extends TestCase
{
    private function createMockApplication(?string $projectConfig = null): Application
    {
        $application = $this->createMock(Application::class);
        $application->projectConfigFile = $projectConfig;

        return $application;
    }

    public function testCreateConfigWithoutFile(): void
    {
        $config = ConfigFactory::create(__DIR__, $this->createMockApplication());
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
        $config = ConfigFactory::create($configDir, $this->createMockApplication());
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
        $config = ConfigFactory::create(__DIR__ . '/_fixtures/maintenance-mode', $this->createMockApplication());
        static::assertTrue($config->maintenance->enabled);
    }

    #[Env('SHOPWARE_STORE_LICENSE_DOMAIN', 'test')]
    public function testLicenseDomainPopulatedByEnv(): void
    {
        $config = ConfigFactory::create(__DIR__, $this->createMockApplication());
        static::assertSame('test', $config->store->licenseDomain);
    }

    public function testExistingConfigWithStoreConfig(): void
    {
        $config = ConfigFactory::create(__DIR__ . '/_fixtures/license-domain', $this->createMockApplication());
        static::assertSame('example.com', $config->store->licenseDomain);
    }

    public function testExistingConfigWithAlwaysClearCache(): void
    {
        $config = ConfigFactory::create(__DIR__ . '/_fixtures/always-clear-cache', $this->createMockApplication());
        static::assertTrue($config->alwaysClearCache);
    }

    public function testExistingConfigWithExtensionOverride(): void
    {
        $config = ConfigFactory::create(__DIR__ . '/_fixtures/extension-override', $this->createMockApplication());
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

    public function testExistingConfigWithExtensionForceUpdates(): void
    {
        $config = ConfigFactory::create(__DIR__ . '/_fixtures/force-updates', $this->createMockApplication());
        static::assertNotEmpty($config->extensionManagement->forceUpdates);

        // Test FroshTest
        static::assertContains('FroshTest', $config->extensionManagement->forceUpdates);
    }

    public function testCreateWithProjectConfigOption(): void
    {
        // Test with absolute path
        $customConfigPath = __DIR__ . '/_fixtures/yml/.shopware-project.yml';
        $config = ConfigFactory::create(__DIR__, $this->createMockApplication($customConfigPath));

        static::assertTrue($config->extensionManagement->enabled);
        static::assertSame(['foo' => 'test'], $config->oneTimeTasks);
    }

    public function testCreateWithProjectConfigOptionRelativePath(): void
    {
        // Test with relative path - should be resolved relative to project dir
        $config = ConfigFactory::create(__DIR__ . '/_fixtures', $this->createMockApplication('yml/.shopware-project.yml'));

        static::assertTrue($config->extensionManagement->enabled);
        static::assertSame(['foo' => 'test'], $config->oneTimeTasks);
    }

    #[Env('SHOPWARE_PROJECT_CONFIG_FILE', '_fixtures/yml/.shopware-project.yml')]
    public function testEnvironmentVariableOverridesProjectConfigOption(): void
    {
        // Environment variable should take precedence over CLI option
        $config = ConfigFactory::create(__DIR__, $this->createMockApplication('some-other-config.yml'));

        // Should load the config from environment variable, not the CLI option
        static::assertSame(['foo' => 'test'], $config->oneTimeTasks);
    }

    public function testCreateWithNonExistentProjectConfig(): void
    {
        // Test with a config file that doesn't exist - should return default config
        $config = ConfigFactory::create(__DIR__, $this->createMockApplication('non-existent-config.yml'));

        static::assertFalse($config->maintenance->enabled);
        static::assertTrue($config->extensionManagement->enabled);
        static::assertSame([], $config->extensionManagement->overrides);
        static::assertSame([], $config->oneTimeTasks);
    }
}
