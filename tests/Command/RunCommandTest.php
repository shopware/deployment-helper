<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Command\RunCommand;
use Shopware\Deployment\Services\HookExecutor;
use Shopware\Deployment\Services\InstallationManager;
use Shopware\Deployment\Services\ShopwareState;
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

        $command = new RunCommand(
            $state,
            $installationManager,
            $this->createMock(UpgradeManager::class),
            $hookExecutor,
            new EventDispatcher()
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

        $command = new RunCommand(
            $state,
            $installationManager,
            $upgradeManager,
            $hookExecutor,
            new EventDispatcher()
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

        $command = new RunCommand(
            $state,
            $installationManager,
            $this->createMock(UpgradeManager::class),
            $hookExecutor,
            new EventDispatcher()
        );

        $tester = new CommandTester($command);
        $tester->execute([]);
    }
}
