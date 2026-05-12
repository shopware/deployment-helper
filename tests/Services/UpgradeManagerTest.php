<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\AccountService;
use Shopware\Deployment\Services\AppHelper;
use Shopware\Deployment\Services\HookExecutor;
use Shopware\Deployment\Services\OneTimeTasks;
use Shopware\Deployment\Services\PluginHelper;
use Shopware\Deployment\Services\ShopwareState;
use Shopware\Deployment\Services\TrackingService;
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
            ->expects($this->exactly(2))
            ->method('execute');

        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->exactly(2))
            ->method('execute');

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects(static::never())->method('refresh');

        $manager = new UpgradeManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(ProcessHelper::class),
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $hookExecutor,
            $oneTimeTasks,
            new ProjectConfiguration(),
            $accountService,
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }

    public function testRunUpdatesVersion(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->expects($this->once())
            ->method('getCurrentVersion')
            ->willReturn('1.0.0');

        $state
            ->expects($this->once())
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
            new ProjectConfiguration(),
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }

    public function testRunUpdatesVersionNoAssetCompile(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->expects($this->once())
            ->method('getCurrentVersion')
            ->willReturn('1.0.0');

        $state
            ->expects($this->once())
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
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
            new ProjectConfiguration(),
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(true, true), $this->createMock(OutputInterface::class));

        static::assertCount(5, $consoleCommands);
        static::assertSame(['messenger:setup-transports'], $consoleCommands[0]);
        static::assertArrayHasKey(1, $consoleCommands);
        static::assertSame(['system:update:finish', '--skip-asset-build'], $consoleCommands[1]);
    }

    #[Env('SALES_CHANNEL_URL', 'http://foo.com')]
    public function testRunWithDifferentSalesChannelUrl(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->expects($this->exactly(2))
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
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
            new ProjectConfiguration(),
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertCount(7, $consoleCommands);
        static::assertArrayHasKey(1, $consoleCommands);
        static::assertSame(['sales-channel:create:storefront', '--name=Storefront', '--url=http://foo.com'], $consoleCommands[1]);
    }

    public function testRunWithMaintenanceMode(): void
    {
        $state = $this->createMock(ShopwareState::class);

        $state
            ->expects($this->once())
            ->method('enableMaintenanceMode');

        $state
            ->expects($this->once())
            ->method('disableMaintenanceMode');

        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $config = new ProjectConfiguration();
        $config->maintenance->enabled = true;

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
            $config,
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertCount(7, $consoleCommands);
        static::assertSame(['cache:pool:clear', 'cache.http', 'cache.object'], $consoleCommands[0]);
        static::assertArrayHasKey(5, $consoleCommands);
        static::assertSame(['cache:pool:clear', 'cache.http', 'cache.object'], $consoleCommands[6]);
    }

    public function testParallelThemeCompileFallsBackWithSingleSalesChannel(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state->method('getActiveStorefrontSalesChannelIds')->willReturn(['abc']);

        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];
        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });
        $processHelper->expects($this->never())->method('consoleParallel');

        $configuration = new ProjectConfiguration();
        $configuration->themeCompile->parallel = true;
        $configuration->themeCompile->workers = 2;

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
            $configuration,
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertContains(['theme:compile', '--active-only'], $consoleCommands);
    }

    public function testParallelThemeCompileFansOutPerSalesChannel(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->method('getActiveStorefrontSalesChannelIds')
            ->willReturn(['aaa', 'bbb', 'ccc']);

        $processHelper = $this->createMock(ProcessHelper::class);
        $callLog = [];
        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$callLog): void {
                $callLog[] = ['type' => 'console', 'args' => $command];
            });

        $processHelper
            ->expects($this->once())
            ->method('consoleParallel')
            ->willReturnCallback(static function (array $commands, int $workers) use (&$callLog): void {
                $callLog[] = ['type' => 'parallel', 'commands' => $commands, 'workers' => $workers];
            });

        $configuration = new ProjectConfiguration();
        $configuration->themeCompile->parallel = true;
        $configuration->themeCompile->workers = 3;

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
            $configuration,
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        $themeCalls = array_values(array_filter(
            $callLog,
            static fn (array $entry): bool => $entry['type'] === 'parallel'
                || ($entry['type'] === 'console' && ($entry['args'][0] ?? null) === 'theme:compile'),
        ));

        static::assertCount(2, $themeCalls, 'expected exactly one serial first-channel call and one parallel batch');
        static::assertSame('console', $themeCalls[0]['type']);
        static::assertSame(['theme:compile', '--sync', '--sales-channel-id=aaa'], $themeCalls[0]['args']);
        static::assertSame('parallel', $themeCalls[1]['type'], 'first channel must run before the parallel batch to write shared assets');
        static::assertSame(3, $themeCalls[1]['workers']);
        static::assertSame(
            [
                ['theme:compile', '--sync', '--keep-assets', '--sales-channel-id=bbb'],
                ['theme:compile', '--sync', '--keep-assets', '--sales-channel-id=ccc'],
            ],
            $themeCalls[1]['commands'],
        );
    }

    #[Env('SHOPWARE_DEPLOYMENT_THEME_COMPILE_WORKERS', '7')]
    public function testParallelThemeCompileWorkerCountOverriddenByEnv(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state->method('getActiveStorefrontSalesChannelIds')->willReturn(['aaa', 'bbb']);

        $processHelper = $this->createMock(ProcessHelper::class);
        $observedWorkers = null;
        $processHelper
            ->expects($this->once())
            ->method('consoleParallel')
            ->willReturnCallback(static function (array $commands, int $workers) use (&$observedWorkers): void {
                $observedWorkers = $workers;
            });

        $configuration = new ProjectConfiguration();
        $configuration->themeCompile->parallel = true;
        $configuration->themeCompile->workers = 2;

        $manager = new UpgradeManager(
            $state,
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $this->createMock(OneTimeTasks::class),
            $configuration,
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertSame(7, $observedWorkers);
    }

    public function testRunWithLicenseDomain(): void
    {
        $oneTimeTasks = $this->createMock(OneTimeTasks::class);
        $oneTimeTasks
            ->expects($this->exactly(2))
            ->method('execute');

        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->exactly(2))
            ->method('execute');

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects(static::once())->method('refresh');

        $configuration = new ProjectConfiguration();
        $configuration->store->licenseDomain = 'example.com';

        $manager = new UpgradeManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(ProcessHelper::class),
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $hookExecutor,
            $oneTimeTasks,
            $configuration,
            $accountService,
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }
}
