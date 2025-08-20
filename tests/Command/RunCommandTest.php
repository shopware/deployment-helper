<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Command\RunCommand;
use Shopware\Deployment\Services\HookExecutor;
use Shopware\Deployment\Services\InstallationManager;
use Shopware\Deployment\Services\ShopwareState;
use Shopware\Deployment\Services\TrackingService;
use Shopware\Deployment\Services\UpgradeManager;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(RunCommand::class)]
class RunCommandTest extends TestCase
{
    public function testInstall(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->expects($this->once())
            ->method('isInstalled')
            ->willReturn(false);

        $state
            ->expects($this->once())
            ->method('getMySqlVersion')
            ->willReturn('5.7.0');

        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->exactly(2))
            ->method('execute');

        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager
            ->expects($this->once())
            ->method('run')
            ->with(self::callback(function ($config) {
                static::assertTrue($config->skipThemeCompile);
                static::assertTrue($config->skipAssetsInstall);
                static::assertEquals(300, $config->timeout);

                return true;
            }));

        $trackingService = $this->createMock(TrackingService::class);
        $trackingService
            ->expects($this->exactly(2))
            ->method('track')
            ->willReturnCallback(function ($event, $data): void {
                if ($event === 'php_version') {
                    static::assertArrayHasKey('php_version', $data);
                    static::assertMatchesRegularExpression('/^\d+\.\d+$/', $data['php_version']);
                } elseif ($event === 'mysql_version') {
                    static::assertArrayHasKey('mysql_version', $data);
                    static::assertEquals('5.7.0', $data['mysql_version']);
                }
            });

        $command = new RunCommand(
            $state,
            $installationManager,
            $this->createMock(UpgradeManager::class),
            $hookExecutor,
            new EventDispatcher(),
            $trackingService
        );

        $tester = new CommandTester($command);
        $tester->execute([
            '--skip-theme-compile' => true,
            '--skip-asset-install' => true,
        ]);
    }

    public function testUpdate(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->expects($this->once())
            ->method('isInstalled')
            ->willReturn(true);
        $state
            ->expects($this->once())
            ->method('getMySqlVersion')
            ->willReturn('5.7.0');

        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->exactly(2))
            ->method('execute');

        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager
            ->expects($this->never())
            ->method('run');

        $upgradeManager = $this->createMock(UpgradeManager::class);
        $upgradeManager
            ->expects($this->once())
            ->method('run')
            ->with(self::callback(function ($config) {
                static::assertFalse($config->skipThemeCompile);
                static::assertTrue($config->skipAssetsInstall);
                static::assertEquals(600, $config->timeout);

                return true;
            }));

        $trackingService = $this->createMock(TrackingService::class);
        $trackingService
            ->expects($this->exactly(2))
            ->method('track')
            ->willReturnCallback(function ($event, $data): void {
                if ($event === 'php_version') {
                    static::assertArrayHasKey('php_version', $data);
                    static::assertMatchesRegularExpression('/^\d+\.\d+$/', $data['php_version']);
                } elseif ($event === 'mysql_version') {
                    static::assertArrayHasKey('mysql_version', $data);
                    static::assertEquals('5.7.0', $data['mysql_version']);
                }
            });

        $command = new RunCommand(
            $state,
            $installationManager,
            $upgradeManager,
            $hookExecutor,
            new EventDispatcher(),
            $trackingService
        );

        $tester = new CommandTester($command);
        $tester->execute([
            '--skip-assets-install' => true,
            '--timeout' => 600,
        ]);
    }

    #[Env('SHOPWARE_DEPLOYMENT_FORCE_REINSTALL', '1')]
    public function testRunWithoutFullyInstalled(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->expects($this->once())
            ->method('isInstalled')
            ->willReturn(true);
        $state
            ->expects($this->once())
            ->method('getPreviousVersion')
            ->willReturn('unknown');
        $state
            ->expects($this->once())
            ->method('getMySqlVersion')
            ->willReturn('10.6.0');

        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->exactly(2))
            ->method('execute');

        $installationManager = $this->createMock(InstallationManager::class);
        $installationManager
            ->expects($this->once())
            ->method('run')
            ->with(self::callback(function ($config) {
                static::assertTrue($config->forceReinstallation);

                return true;
            }));

        $trackingService = $this->createMock(TrackingService::class);
        $trackingService
            ->expects($this->exactly(2))
            ->method('track')
            ->willReturnCallback(function ($event, $data): void {
                if ($event === 'php_version') {
                    static::assertArrayHasKey('php_version', $data);
                    static::assertMatchesRegularExpression('/^\d+\.\d+$/', $data['php_version']);
                } elseif ($event === 'mysql_version') {
                    static::assertArrayHasKey('mysql_version', $data);
                    static::assertEquals('10.6.0', $data['mysql_version']);
                }
            });

        $command = new RunCommand(
            $state,
            $installationManager,
            $this->createMock(UpgradeManager::class),
            $hookExecutor,
            new EventDispatcher(),
            $trackingService
        );

        $tester = new CommandTester($command);
        $tester->execute([]);
    }
}
