<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Config\ProjectExtensionManagement;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\AppHelper;
use Shopware\Deployment\Services\AppLoader;

#[CoversClass(AppHelper::class)]
class AppHelperTest extends TestCase
{
    public function testInstallManagementDisabled(): void
    {
        $config = new ProjectConfiguration();
        $config->extensionManagement->enabled = false;

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects($this->never())
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
            ->expects($this->once())
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
            ->expects($this->once())
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
            ->expects($this->never())
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
            ->expects($this->never())
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
            ->expects($this->never())
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

    public function testForceUpdateHappensWhileSameVersion(): void
    {
        $config = new ProjectConfiguration();
        $config->extensionManagement->forceUpdates = ['TestApp'];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects($this->once())
            ->method('console')
            ->with(['app:refresh', '--force']);

        $version = '0.0.1';
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociativeIndexed')
            ->willReturn([
                'TestApp' => ['name' => 'TestApp', 'version' => $version, 'active' => false],
            ]);

        $appHelper = new AppHelper(
            $this->createAppLoader($version),
            $processHelper,
            $connection,
            $config,
        );

        $appHelper->updateApps();
    }

    public function testNoUpdateNotForcedWithSameVersion(): void
    {
        $config = new ProjectConfiguration();

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects($this->never())
            ->method('console');

        $version = '0.0.1';
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociativeIndexed')
            ->willReturn([
                'TestApp' => ['name' => 'TestApp', 'version' => $version, 'active' => false],
            ]);

        $appHelper = new AppHelper(
            $this->createAppLoader($version),
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
            ->expects($this->once())
            ->method('console')
            ->with(['app:refresh', '--force']);

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

    public function testDeactivate(): void
    {
        $config = new ProjectConfiguration();
        $config->extensionManagement->overrides['TestApp'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INACTIVE];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects($this->once())
            ->method('console')
            ->with(['app:deactivate', 'TestApp']);

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'TestApp', 'version' => '0.0.1', 'active' => true],
            ]);

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $connection,
            $config,
        );

        $appHelper->deactivateApps();
    }

    public function testDeactivateDoesNothingWhenDeactivated(): void
    {
        $config = new ProjectConfiguration();
        $config->extensionManagement->overrides['TestApp'] = ['state' => ProjectExtensionManagement::LIFECYCLE_STATE_INACTIVE];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects($this->never())
            ->method('console');

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'TestApp', 'version' => '0.0.1', 'active' => false],
            ]);

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $connection,
            $config,
        );

        $appHelper->deactivateApps();
    }

    public function testDeactivateDoesNothingWhenAppIsFine(): void
    {
        $config = new ProjectConfiguration();

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects($this->never())
            ->method('console');

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'TestApp', 'version' => '0.0.1', 'active' => true],
            ]);

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $connection,
            $config,
        );

        $appHelper->deactivateApps();
    }

    public function testUninstall(): void
    {
        $config = new ProjectConfiguration();
        $config->extensionManagement->overrides['TestApp'] = ['state' => 'remove'];

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects($this->once())
            ->method('console')
            ->with(['app:uninstall', 'TestApp']);

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'TestApp', 'version' => '0.0.1', 'active' => true],
            ]);

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $connection,
            $config,
        );

        $appHelper->removeApps();
    }

    public function testUninstallWhenNotMatching(): void
    {
        $config = new ProjectConfiguration();

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->expects($this->never())
            ->method('console');

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'TestApp', 'version' => '0.0.1', 'active' => true],
            ]);

        $appHelper = new AppHelper(
            $this->createAppLoader(),
            $processHelper,
            $connection,
            $config,
        );

        $appHelper->removeApps();
    }

    public function createAppLoader(?string $version = '1.0.0'): AppLoader&MockObject
    {
        $appLoader = $this->createMock(AppLoader::class);

        $appLoader
            ->method('all')
            ->willReturn([
                ['name' => 'TestApp', 'version' => $version],
            ]);

        return $appLoader;
    }
}
