<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\AccountService;
use Shopware\Deployment\Services\AppHelper;
use Shopware\Deployment\Services\HookExecutor;
use Shopware\Deployment\Services\InstallationManager;
use Shopware\Deployment\Services\OpenSearchHelper;
use Shopware\Deployment\Services\PluginHelper;
use Shopware\Deployment\Services\ShopwareState;
use Shopware\Deployment\Services\TrackingService;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Output\OutputInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(InstallationManager::class)]
#[Env('APP_URL', 'http://localhost')]
class InstallationManagerTest extends TestCase
{
    public function testRun(): void
    {
        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->exactly(2))
            ->method('execute');

        $manager = new InstallationManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(Connection::class),
            $this->createMock(ProcessHelper::class),
            $this->createMock(OpenSearchHelper::class),
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $hookExecutor,
            new ProjectConfiguration(),
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }

    public function testRunNoStorefront(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state->method('isStorefrontInstalled')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('DELETE FROM sales_channel WHERE type_id = 0xf183ee5650cf4bdb8a774337575067a6');

        $manager = new InstallationManager(
            $state,
            $connection,
            $this->createMock(ProcessHelper::class),
            $this->createMock(OpenSearchHelper::class),
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            new ProjectConfiguration(),
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }

    public function testRunDisabledAssetCopyAndThemeCompile(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state->method('isStorefrontInstalled')
            ->willReturn(true);

        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects(static::never())->method('refresh');

        $openSearchHelper = $this->createMock(OpenSearchHelper::class);
        $openSearchHelper->expects(static::never())->method('prepareShopIndex');

        $manager = new InstallationManager(
            $state,
            $this->createMock(Connection::class),
            $processHelper,
            $openSearchHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            new ProjectConfiguration(),
            $accountService,
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(true, true), $this->createMock(OutputInterface::class));

        static::assertCount(8, $consoleCommands);
        static::assertSame(['system:install', '--create-database', '--shop-locale=en-GB', '--shop-currency=EUR', '--force', '--no-assign-theme', '--skip-assets-install'], $consoleCommands[0]);
        static::assertSame(['cache:clear'], $consoleCommands[7]);
    }

    public function testRunWithLicenseDomain(): void
    {
        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->exactly(2))
            ->method('execute');

        $configuration = new ProjectConfiguration();
        $configuration->store->licenseDomain = 'example.com';

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects($this->once())->method('refresh');

        $manager = new InstallationManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(Connection::class),
            $this->createMock(ProcessHelper::class),
            $this->createMock(OpenSearchHelper::class),
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $hookExecutor,
            $configuration,
            $accountService,
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }

    public function testRunWithForceReinstall(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $accountService = $this->createMock(AccountService::class);
        $accountService->expects(static::never())->method('refresh');

        $trackingService = $this->createMock(TrackingService::class);
        $trackingService->expects(static::once())->method('persistId');

        $openSearchHelper = $this->createMock(OpenSearchHelper::class);
        $openSearchHelper->expects(static::never())->method('prepareShopIndex');

        $configuration = new ProjectConfiguration();

        $manager = new InstallationManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(Connection::class),
            $processHelper,
            $openSearchHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $configuration,
            $accountService,
            $trackingService,
        );

        $manager->run(new RunConfiguration(true, true, forceReinstallation: true), $this->createMock(OutputInterface::class));

        static::assertCount(4, $consoleCommands);
        static::assertSame(['system:install', '--create-database', '--shop-locale=en-GB', '--shop-currency=EUR', '--force', '--no-assign-theme', '--skip-assets-install', '--drop-database'], $consoleCommands[0]);
    }

    public function testRunExecutesInstallBeforeOpenSearchBootstrap(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $configuration = new ProjectConfiguration();
        $configuration->openSearch->indexIfEmpty = true;

        $openSearchHelper = $this->createMock(OpenSearchHelper::class);
        $openSearchHelper
            ->expects($this->once())
            ->method('prepareShopIndex')
            ->willReturn(OpenSearchHelper::SHOP_INDEX_ACTION_REINDEX);

        $manager = new InstallationManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(Connection::class),
            $processHelper,
            $openSearchHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $configuration,
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertSame('system:install', $consoleCommands[0][0]);
        static::assertSame(['es:index', '--no-queue'], $consoleCommands[\count($consoleCommands) - 1]);
    }

    public function testRunTriggersOpenSearchReindexAtTheEndWhenEnabledAndAliasWasCreated(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $configuration = new ProjectConfiguration();
        $configuration->openSearch->indexIfEmpty = true;

        $openSearchHelper = $this->createMock(OpenSearchHelper::class);
        $openSearchHelper->expects(static::once())->method('prepareShopIndex')->willReturn(OpenSearchHelper::SHOP_INDEX_ACTION_REINDEX);

        $manager = new InstallationManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(Connection::class),
            $processHelper,
            $openSearchHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $configuration,
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertSame(['es:index', '--no-queue'], $consoleCommands[\count($consoleCommands) - 1]);
    }

    public function testRunClearsCacheAndCompilesThemeAfterInstallingExtensions(): void
    {
        $state = $this->createMock(ShopwareState::class);
        $state->method('isStorefrontInstalled')->willReturn(true);

        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $openSearchHelper = $this->createMock(OpenSearchHelper::class);
        $openSearchHelper->expects(static::never())->method('prepareShopIndex');

        $manager = new InstallationManager(
            $state,
            $this->createMock(Connection::class),
            $processHelper,
            $openSearchHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            new ProjectConfiguration(),
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertSame(['cache:clear'], $consoleCommands[\count($consoleCommands) - 2]);
        static::assertSame(['theme:compile', '--active-only'], $consoleCommands[\count($consoleCommands) - 1]);
    }

    public function testRunTriggersOpenSearchMappingUpdateAtTheEndWhenMappingIsIncomplete(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $configuration = new ProjectConfiguration();
        $configuration->openSearch->indexIfEmpty = true;

        $openSearchHelper = $this->createMock(OpenSearchHelper::class);
        $openSearchHelper->expects(static::once())->method('prepareShopIndex')->willReturn(OpenSearchHelper::SHOP_INDEX_ACTION_UPDATE_MAPPING);

        $manager = new InstallationManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(Connection::class),
            $processHelper,
            $openSearchHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $configuration,
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertSame(['es:mapping:update'], $consoleCommands[\count($consoleCommands) - 1]);
    }

    #[Env('SHOPWARE_DEPLOYMENT_OPENSEARCH_PREPARE_INDEX', '0')]
    public function testRunSkipsOpenSearchPreparationWhenEnvDisablesIt(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $configuration = new ProjectConfiguration();
        $configuration->openSearch->indexIfEmpty = true;

        $openSearchHelper = $this->createMock(OpenSearchHelper::class);
        $openSearchHelper->expects(static::never())->method('prepareShopIndex');

        $manager = new InstallationManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(Connection::class),
            $processHelper,
            $openSearchHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $configuration,
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        $openSearchCommands = array_values(array_filter(
            $consoleCommands,
            static fn (array $command): bool => $command === ['es:index', '--no-queue'] || $command === ['es:mapping:update']
        ));

        static::assertSame([], $openSearchCommands);
    }
}
