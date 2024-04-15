<?php declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Services\AppLoader;

class AppLoaderTest extends TestCase
{
    public function testNotExistingAppsFolder(): void
    {
        $appLoader = new AppLoader(__DIR__);

        static::assertSame([], $appLoader->all());
    }

    public function testLocalApp(): void
    {
        $appLoader = new AppLoader(__DIR__ . '/_fixtures/correct');

        static::assertSame([
            ['name' => 'TestApp', 'version' => '1.0.1'],
        ], $appLoader->all());
    }

    public function testLocalAppInvalidApp(): void
    {
        $appLoader = new AppLoader(__DIR__ . '/_fixtures/invalid');

        static::assertSame([], $appLoader->all());
    }

    public function testLoadFromComposer(): void
    {
        $before = InstalledVersions::getAllRawData();

        InstalledVersions::reload([
            'versions' => [
                'foo/foo' => [
                    'name' => 'foo/foo',
                    'version' => '1.0.0',
                    'type' => 'shopware-app',
                    'install_path' => __DIR__ . '/_fixtures/correct/custom/apps/TestApp',
                ],
            ],
        ]);

        $appLoader = new AppLoader(__DIR__);

        static::assertSame([
            ['name' => 'TestApp', 'version' => '1.0.1'],
        ], $appLoader->all());

        InstalledVersions::reload($before);
    }
}
