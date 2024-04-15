<?php declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\AppHelper;
use Shopware\Deployment\Services\AppLoader;

class AppHelperTest extends TestCase
{
    public function testInstallManagementDisabled(): void
    {
        $config = new ProjectConfiguration();
        $config->extensionManagement->enabled = false;

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects(static::never())
            ->method('console');

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $this->createMock(Connection::class),
            $config,
        );

        $appHelper->installApps();
    }

    public function testInstallsWhenNotInstalled(): void
    {
        $config = new ProjectConfiguration();

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects(static::once())
            ->method('console')
            ->with(['app:install', 'TestApp', '--activate', '--force']);

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $this->createMock(Connection::class),
            $config,
        );

        $appHelper->installApps();
    }

    public function testInstallsNotActive(): void
    {
        $config = new ProjectConfiguration();

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects(static::once())
            ->method('console')
            ->with(['app:activate', 'TestApp']);

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociativeIndexed')
            ->willReturn([
                'TestApp' => ['name' => 'TestApp', 'version' => '1.0.0', 'active' => false],
            ]);

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $connection,
            $config,
        );

        $appHelper->installApps();
    }

    public function testUpdateManagementDisabled(): void
    {
        $config = new ProjectConfiguration();
        $config->extensionManagement->enabled = false;

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects(static::never())
            ->method('console');

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $this->createMock(Connection::class),
            $config,
        );

        $appHelper->updateApps();
    }

    public function testUpdateNotInstalledSkipped(): void
    {
        $config = new ProjectConfiguration();

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects(static::never())
            ->method('console');

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $this->createMock(Connection::class),
            $config,
        );

        $appHelper->updateApps();
    }

    public function testUpdateNoUpdateSkipped(): void
    {
        $config = new ProjectConfiguration();

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects(static::never())
            ->method('console');

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociativeIndexed')
            ->willReturn([
                'TestApp' => ['name' => 'TestApp', 'version' => '1.0.0', 'active' => false],
            ]);

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $connection,
            $config,
        );

        $appHelper->updateApps();
    }

    public function testUpdate(): void
    {
        $config = new ProjectConfiguration();

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects(static::once())
            ->method('console')
            ->with(['app:update', 'TestApp']);

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociativeIndexed')
            ->willReturn([
                'TestApp' => ['name' => 'TestApp', 'version' => '0.0.1', 'active' => false],
            ]);

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $connection,
            $config,
        );

        $appHelper->updateApps();
    }

    public function createAppLoader(): AppLoader&MockObject
    {
        $appLoader = $this->createMock(AppLoader::class);

        $appLoader
            ->method('all')
            ->willReturn([
                ['name' => 'TestApp', 'version' => '1.0.0'],
            ]);

        return $appLoader;
    }
}
