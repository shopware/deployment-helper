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
use Shopware\Deployment\Services\Plugin\PluginHelper;
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

    public function testRunDoesNotIndexOpenSearch(): void
    {
        $keys = ['SHOPWARE_ES_INDEXING_ENABLED', 'OPENSEARCH_URL', 'ADMIN_OPENSEARCH_URL'];
        $environment = [];
        foreach ($keys as $key) {
            $environment[$key] = [
                'serverExists' => \array_key_exists($key, $_SERVER),
                'serverValue' => $_SERVER[$key] ?? null,
                'envExists' => \array_key_exists($key, $_ENV),
                'envValue' => $_ENV[$key] ?? null,
            ];
            unset($_SERVER[$key], $_ENV[$key]);
        }

        try {
            $_SERVER['SHOPWARE_ES_INDEXING_ENABLED'] = '1';
            $_SERVER['OPENSEARCH_URL'] = 'http://opensearch:9200';
            $_SERVER['ADMIN_OPENSEARCH_URL'] = 'http://admin-opensearch:9200';

            $processHelper = $this->createMock(ProcessHelper::class);
            $consoleCommands = [];
            $processHelper
                ->method('console')
                ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                    $consoleCommands[] = $command;
                });

            $configuration = new ProjectConfiguration();
            $configuration->openSearchIndexing->enabled = true;

            $manager = new UpgradeManager(
                $this->createMock(ShopwareState::class),
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

            static::assertNotContains(['es:index', '--no-queue'], $consoleCommands);
            static::assertNotContains(['es:admin:index', '--no-queue'], $consoleCommands);
        } finally {
            foreach ($environment as $key => $values) {
                unset($_SERVER[$key], $_ENV[$key]);

                if ($values['serverExists']) {
                    $_SERVER[$key] = $values['serverValue'];
                }

                if ($values['envExists']) {
                    $_ENV[$key] = $values['envValue'];
                }
            }
        }
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
        $state->method('getActiveStorefrontThemeAssignments')->willReturn([
            ['salesChannelId' => 'abc', 'themeId' => 'theme-a'],
        ]);

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

    public function testParallelThemeCompileFallsBackWhenEachChannelHasDistinctTheme(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state->method('getActiveStorefrontThemeAssignments')->willReturn([
            ['salesChannelId' => 'aaa', 'themeId' => 'theme-a'],
            ['salesChannelId' => 'bbb', 'themeId' => 'theme-b'],
        ]);
        $state->method('getCurrentVersion')->willReturn('6.6.10.3');

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
            ->method('getActiveStorefrontThemeAssignments')
            ->willReturn([
                ['salesChannelId' => 'aaa', 'themeId' => 'theme-a'],
                ['salesChannelId' => 'bbb', 'themeId' => 'theme-a'],
                ['salesChannelId' => 'ccc', 'themeId' => 'theme-a'],
            ]);
        $state->method('getCurrentVersion')->willReturn('6.6.10.3');

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

        static::assertCount(2, $themeCalls, 'expected exactly one serial seed call and one parallel batch');
        static::assertSame('console', $themeCalls[0]['type']);
        static::assertSame(['theme:compile', '--sync', '--only=aaa'], $themeCalls[0]['args']);
        static::assertSame('parallel', $themeCalls[1]['type'], 'seed channel must run before the parallel batch to write shared assets');
        static::assertSame(2, $themeCalls[1]['workers'], 'workers are capped by remaining channel count');
        static::assertSame(
            [
                ['theme:compile', '--sync', '--keep-assets', '--only=bbb'],
                ['theme:compile', '--sync', '--keep-assets', '--only=ccc'],
            ],
            $themeCalls[1]['commands'],
        );
    }

    public function testParallelThemeCompileSeedsEachDistinctThemeBeforeParallelRest(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->method('getActiveStorefrontThemeAssignments')
            ->willReturn([
                ['salesChannelId' => 'sc-a1', 'themeId' => 'theme-a'],
                ['salesChannelId' => 'sc-b1', 'themeId' => 'theme-b'],
                ['salesChannelId' => 'sc-a2', 'themeId' => 'theme-a'],
                ['salesChannelId' => 'sc-b2', 'themeId' => 'theme-b'],
            ]);
        $state->method('getCurrentVersion')->willReturn('6.6.10.3');

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
        $configuration->themeCompile->workers = 4;

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

        static::assertCount(3, $themeCalls, 'expected two seed compiles and one parallel batch');
        static::assertSame(['theme:compile', '--sync', '--only=sc-a1'], $themeCalls[0]['args']);
        static::assertSame(['theme:compile', '--sync', '--only=sc-b1'], $themeCalls[1]['args']);
        static::assertSame('parallel', $themeCalls[2]['type']);
        static::assertSame(2, $themeCalls[2]['workers']);
        static::assertSame(
            [
                ['theme:compile', '--sync', '--keep-assets', '--only=sc-a2'],
                ['theme:compile', '--sync', '--keep-assets', '--only=sc-b2'],
            ],
            $themeCalls[2]['commands'],
        );
    }

    public function testParallelThemeCompileOmitsSyncOnShopwareBefore661(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state
            ->method('getActiveStorefrontThemeAssignments')
            ->willReturn([
                ['salesChannelId' => 'aaa', 'themeId' => 'theme-a'],
                ['salesChannelId' => 'bbb', 'themeId' => 'theme-a'],
            ]);
        // 6.5.6+ has --only; --sync arrives only in 6.6.1.
        $state->method('getCurrentVersion')->willReturn('6.5.8.18');

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

        static::assertSame(['theme:compile', '--only=aaa'], $themeCalls[0]['args']);
        static::assertSame(
            [['theme:compile', '--keep-assets', '--only=bbb']],
            $themeCalls[1]['commands'],
        );
    }

    public function testParallelThemeCompileFallsBackWhenOnlyOptionUnavailable(): void
    {
        $state = $this->createMock(ShopwareState::class);
        // --only was introduced in 6.5.6; version check runs before loading assignments.
        $state->method('getCurrentVersion')->willReturn('6.5.5.2');
        $state->expects($this->never())->method('getActiveStorefrontThemeAssignments');

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

        $output = $this->createMock(OutputInterface::class);
        $messages = [];
        $output->method('writeln')->willReturnCallback(static function (string|iterable $message) use (&$messages): void {
            $messages[] = $message;
        });

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

        $manager->run(new RunConfiguration(), $output);

        static::assertContains(['theme:compile', '--active-only'], $consoleCommands);

        $warned = false;
        foreach ($messages as $message) {
            if (\is_string($message) && str_contains($message, 'Parallel theme compile requires Shopware 6.5.6+')) {
                $warned = true;
                break;
            }
        }
        static::assertTrue($warned);
    }

    public function testParallelThemeCompileUsesConfiguredWorkerCount(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state->method('getActiveStorefrontThemeAssignments')->willReturn([
            ['salesChannelId' => 'aaa', 'themeId' => 'theme-a'],
            ['salesChannelId' => 'bbb', 'themeId' => 'theme-a'],
            ['salesChannelId' => 'ccc', 'themeId' => 'theme-a'],
            ['salesChannelId' => 'ddd', 'themeId' => 'theme-a'],
            ['salesChannelId' => 'eee', 'themeId' => 'theme-a'],
            ['salesChannelId' => 'fff', 'themeId' => 'theme-a'],
            ['salesChannelId' => 'ggg', 'themeId' => 'theme-a'],
            ['salesChannelId' => 'hhh', 'themeId' => 'theme-a'],
        ]);
        $state->method('getCurrentVersion')->willReturn('6.6.10.3');

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
        $configuration->themeCompile->workers = 7;

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
