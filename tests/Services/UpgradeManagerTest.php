<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\AppHelper;
use Shopware\Deployment\Services\HookExecutor;
use Shopware\Deployment\Services\OneTimeTasks;
use Shopware\Deployment\Services\PluginHelper;
use Shopware\Deployment\Services\ShopwareState;
use Shopware\Deployment\Services\UpgradeManager;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Output\OutputInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(UpgradeManager::class)]
#[CoversClass(RunConfiguration::class)]
class UpgradeManagerTest extends TestCase
{
    public function testRun(): void
    {
        $oneTimeTasks = $this->createMock(OneTimeTasks::class);
        $oneTimeTasks
            ->expects($this->once())
            ->method('execute');

        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->exactly(2))
            ->method('execute');

        $manager = new UpgradeManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(ProcessHelper::class),
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $hookExecutor,
            $oneTimeTasks,
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }

    public function testRunUpdatesVersion(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->expects($this->exactly(3))
            ->method('getCurrentVersion')
            ->willReturn('1.0.0');

        $state
            ->expects($this->exactly(2))
            ->method('getPreviousVersion')
            ->willReturn('0.0.0');

        $state
            ->expects($this->once())
            ->method('setVersion')
            ->with('1.0.0');

        $manager = new UpgradeManager(
            $state,
            $this->createMock(ProcessHelper::class),
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }

    public function testRunUpdatesVersionNoAssetCompile(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->expects($this->exactly(3))
            ->method('getCurrentVersion')
            ->willReturn('1.0.0');

        $state
            ->expects($this->exactly(2))
            ->method('getPreviousVersion')
            ->willReturn('0.0.0');

        $state
            ->expects($this->once())
            ->method('setVersion')
            ->with('1.0.0');

        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
        );

        $manager->run(new RunConfiguration(true, true), $this->createMock(OutputInterface::class));

        static::assertCount(4, $consoleCommands);
        static::assertSame(['system:update:finish', '--skip-asset-build'], $consoleCommands[0]);
    }

    #[Env('SALES_CHANNEL_URL', 'http://foo.com')]
    public function testRunWithDifferentSalesChannelUrl(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->expects($this->once())
            ->method('isStorefrontInstalled')
            ->willReturn(true);

        $state
            ->expects($this->once())
            ->method('isSalesChannelExisting')
            ->with('http://foo.com')
            ->willReturn(false);

        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertCount(5, $consoleCommands);
        static::assertSame(['sales-channel:create:storefront', '--name=Storefront', '--url=http://foo.com'], $consoleCommands[0]);
    }
}
