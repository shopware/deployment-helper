<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Config\ProjectConfiguration;
use Shopware\Deployment\Helper\ProcessHelper;
use Shopware\Deployment\Services\AccountService;
use Shopware\Deployment\Services\AppHelper;
use Shopware\Deployment\Services\HookExecutor;
use Shopware\Deployment\Services\InstallationManager;
use Shopware\Deployment\Services\Plugin\PluginHelper;
use Shopware\Deployment\Services\ShopwareState;
use Shopware\Deployment\Services\TrackingService;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Output\OutputInterface;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(InstallationManager::class)]
#[Env('APP_URL', 'http://localhost')]
class InstallationManagerTest extends TestCase
{
    /**
     * @var array<string, array{serverExists: bool, serverValue: mixed, envExists: bool, envValue: mixed}>
     */
    private array $environment = [];

    protected function setUp(): void
    {
        foreach (['SHOPWARE_ES_INDEXING_ENABLED', 'OPENSEARCH_URL', 'ADMIN_OPENSEARCH_URL'] as $key) {
            $this->environment[$key] = [
                'serverExists' => \array_key_exists($key, $_SERVER),
                'serverValue' => $_SERVER[$key] ?? null,
                'envExists' => \array_key_exists($key, $_ENV),
                'envValue' => $_ENV[$key] ?? null,
            ];

            unset($_SERVER[$key], $_ENV[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $environment) {
            unset($_SERVER[$key], $_ENV[$key]);

            if ($environment['serverExists']) {
                $_SERVER[$key] = $environment['serverValue'];
            }

            if ($environment['envExists']) {
                $_ENV[$key] = $environment['envValue'];
            }
        }
    }

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

        $manager = new InstallationManager(
            $state,
            $this->createMock(Connection::class),
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            new ProjectConfiguration(),
            $accountService,
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(true, true), $this->createMock(OutputInterface::class));

        static::assertCount(7, $consoleCommands);
        static::assertSame(['system:install', '--create-database', '--shop-locale=en-GB', '--shop-currency=EUR', '--force', '--no-assign-theme', '--skip-assets-install'], $consoleCommands[0]);
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
        $_SERVER['SHOPWARE_ES_INDEXING_ENABLED'] = '1';
        $_SERVER['OPENSEARCH_URL'] = 'http://opensearch:9200';

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

        $configuration = new ProjectConfiguration();
        $configuration->openSearchIndexing->enabled = true;

        $manager = new InstallationManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(Connection::class),
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $configuration,
            $accountService,
            $trackingService,
        );

        $manager->run(new RunConfiguration(true, true, forceReinstallation: true), $this->createMock(OutputInterface::class));

        static::assertSame([
            ['system:install', '--create-database', '--shop-locale=en-GB', '--shop-currency=EUR', '--force', '--no-assign-theme', '--skip-assets-install', '--drop-database'],
            ['user:create', 'admin', '--password=shopware'],
            ['messenger:setup-transports'],
            ['plugin:refresh'],
            ['es:index', '--no-queue'],
        ], $consoleCommands);
    }

    #[Env('INSTALL_ADMIN_EMAIL', 'admin@example.com')]
    public function testRunWithAdminEmail(): void
    {
        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];

        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $manager = new InstallationManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(Connection::class),
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            new ProjectConfiguration(),
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertSame(['user:create', 'admin', '--password=shopware', '--email=admin@example.com'], $consoleCommands[1]);
    }

    /**
     * @param list<list<string>> $expectedIndexCommands
     */
    #[DataProvider('openSearchIndexingProvider')]
    public function testRunOpenSearchIndexing(bool $enabled, ?string $indexingEnabled, ?string $openSearchUrl, ?string $adminOpenSearchUrl, array $expectedIndexCommands): void
    {
        if ($indexingEnabled !== null) {
            $_SERVER['SHOPWARE_ES_INDEXING_ENABLED'] = $indexingEnabled;
        }

        if ($openSearchUrl !== null) {
            $_SERVER['OPENSEARCH_URL'] = $openSearchUrl;
        }

        if ($adminOpenSearchUrl !== null) {
            $_SERVER['ADMIN_OPENSEARCH_URL'] = $adminOpenSearchUrl;
        }

        $processHelper = $this->createMock(ProcessHelper::class);
        $consoleCommands = [];
        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command) use (&$consoleCommands): void {
                $consoleCommands[] = $command;
            });

        $configuration = new ProjectConfiguration();
        $configuration->openSearchIndexing->enabled = $enabled;

        $manager = new InstallationManager(
            $this->createMock(ShopwareState::class),
            $this->createMock(Connection::class),
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $this->createMock(HookExecutor::class),
            $configuration,
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));

        static::assertSame($expectedIndexCommands, \array_slice($consoleCommands, 4));
    }

    public function testRunPropagatesOpenSearchIndexingFailureBeforePostInstallSteps(): void
    {
        $_SERVER['SHOPWARE_ES_INDEXING_ENABLED'] = '1';
        $_SERVER['OPENSEARCH_URL'] = 'http://opensearch:9200';

        $processHelper = $this->createMock(ProcessHelper::class);
        $processHelper
            ->method('console')
            ->willReturnCallback(static function (array $command): void {
                if ($command === ['es:index', '--no-queue']) {
                    throw new \RuntimeException('OpenSearch indexing failed');
                }
            });

        $state = $this->createMock(ShopwareState::class);
        $state->expects(static::never())->method('setVersion');

        $hookExecutor = $this->createMock(HookExecutor::class);
        $hookExecutor
            ->expects($this->once())
            ->method('execute')
            ->with(HookExecutor::HOOK_PRE_INSTALL);

        $configuration = new ProjectConfiguration();
        $configuration->openSearchIndexing->enabled = true;

        $manager = new InstallationManager(
            $state,
            $this->createMock(Connection::class),
            $processHelper,
            $this->createMock(PluginHelper::class),
            $this->createMock(AppHelper::class),
            $hookExecutor,
            $configuration,
            $this->createMock(AccountService::class),
            $this->createMock(TrackingService::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenSearch indexing failed');

        $manager->run(new RunConfiguration(), $this->createMock(OutputInterface::class));
    }

    /**
     * @return iterable<string, array{bool, ?string, ?string, ?string, list<list<string>>}>
     */
    public static function openSearchIndexingProvider(): iterable
    {
        yield 'storefront only' => [true, '1', 'http://opensearch:9200', null, [['es:index', '--no-queue']]];
        yield 'admin only' => [true, '1', null, 'http://admin-opensearch:9200', [['es:admin:index', '--no-queue']]];
        yield 'storefront and admin' => [true, '1', 'http://opensearch:9200', 'http://admin-opensearch:9200', [['es:index', '--no-queue'], ['es:admin:index', '--no-queue']]];
        yield 'disabled opt-in' => [false, '1', 'http://opensearch:9200', 'http://admin-opensearch:9200', []];
        yield 'indexing flag is not enabled' => [true, 'true', 'http://opensearch:9200', 'http://admin-opensearch:9200', []];
        yield 'indexing flag is absent' => [true, null, 'http://opensearch:9200', 'http://admin-opensearch:9200', []];
        yield 'storefront URL is absent' => [true, '1', null, null, []];
    }
}
