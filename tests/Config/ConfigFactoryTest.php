<?php declare(strict_types=1);

namespace Shopware\Deployment\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Deployment\Config\ConfigFactory;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigFactory::class)]
class ConfigFactoryTest extends TestCase
{
    public function testCreateConfigWithoutFile(): void
    {
        $config = ConfigFactory::create(__DIR__);
        static::assertTrue($config->extensionManagement->enabled);
        static::assertSame([], $config->extensionManagement->excluded);
        static::assertSame([], $config->oneTimeTasks);
        static::assertSame('', $config->hooks->pre);
    }

    public function testExistingConfig(): void
    {
        $config = ConfigFactory::create(__DIR__ . '/_fixtures');
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
}
