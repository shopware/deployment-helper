<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Event\PostDeploy;
use Shopware\Deployment\Integration\UsageDataConsentSubscriber;
use Shopware\Deployment\Services\SystemConfigHelper;
use Shopware\Deployment\Struct\RunConfiguration;
use Symfony\Component\Console\Output\NullOutput;
use Zalas\PHPUnit\Globals\Attribute\Env;

#[CoversClass(UsageDataConsentSubscriber::class)]
class UsageDataConsentSubscriberTest extends TestCase
{
    private SystemConfigHelper&MockObject $systemConfigHelper;

    private Connection&MockObject $connection;

    private UsageDataConsentSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->systemConfigHelper = $this->createMock(SystemConfigHelper::class);
        $this->connection = $this->createMock(Connection::class);

        $this->subscriber = new UsageDataConsentSubscriber($this->systemConfigHelper, $this->connection);
    }

    #[DataProvider('provideConsent')]
    #[Env('SHOPWARE_USAGE_DATA_CONSENT', value: '')]
    public function testInvokeWithSystemConfigOnly(string $consent, bool $shouldBeCalled): void
    {
        $_SERVER['SHOPWARE_USAGE_DATA_CONSENT'] = $consent;

        $this->systemConfigHelper
            ->expects($shouldBeCalled ? $this->once() : $this->never())
            ->method('set')
            ->with('core.usageData.consentState', $consent);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(false);
        $this->connection->method('createSchemaManager')->willReturn($schemaManager);
        $this->connection->expects($this->never())->method('executeStatement');

        $event = new PostDeploy(new RunConfiguration(), new NullOutput());
        $this->subscriber->__invoke($event);
    }

    public static function provideConsent(): \Generator
    {
        yield 'requested' => ['requested', true];
        yield 'accepted' => ['accepted', true];
        yield 'revoked' => ['revoked', true];
    }

    #[Env('SHOPWARE_USAGE_DATA_CONSENT', 'invalid')]
    public function testInvokeWithInvalidConsent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for SHOPWARE_USAGE_DATA_CONSENT. Must be one of: requested, accepted, revoked');

        $this->systemConfigHelper
            ->expects($this->never())
            ->method('set');

        $event = new PostDeploy(new RunConfiguration(), new NullOutput());
        $this->subscriber->__invoke($event);
    }

    public function testInvokeWithNoConsent(): void
    {
        $this->systemConfigHelper
            ->expects($this->never())
            ->method('set');

        $event = new PostDeploy(new RunConfiguration(), new NullOutput());
        $this->subscriber->__invoke($event);
    }

    #[Env('SHOPWARE_USAGE_DATA_CONSENT', 'requested')]
    public function testInvokeWithRequestedStateWillNotUpdateConsentTable(): void
    {
        $this->connection->expects($this->never())->method('executeQuery');
        $this->connection->expects($this->never())->method('executeStatement');

        $event = new PostDeploy(new RunConfiguration(), new NullOutput());
        $this->subscriber->__invoke($event);
    }

    #[DataProvider('provideForConsentInsert')]
    #[Env('SHOPWARE_USAGE_DATA_CONSENT', '')]
    public function testInsertNewConsentState(string $consent, string $written): void
    {
        $_SERVER['SHOPWARE_USAGE_DATA_CONSENT'] = $consent;

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $this->connection->method('createSchemaManager')->willReturn($schemaManager);
        $this->connection->expects($this->once())->method('fetchAssociative')->willReturn(false);
        $this->connection->expects($this->once())->method('executeStatement')->with(
            Assert::stringStartsWith('INSERT INTO `consent_state`'),
            Assert::logicalAnd(
                Assert::isArray(),
                Assert::arrayHasKey('id'),
                Assert::containsEqual($written)
            )
        );

        $event = new PostDeploy(new RunConfiguration(), new NullOutput());
        $this->subscriber->__invoke($event);
    }

    public static function provideForConsentInsert(): \Generator
    {
        yield 'accepted' => ['accepted', 'accepted'];
        yield 'revoked' => ['revoked', 'declined'];
    }

    #[DataProvider('provideForConsentUpdate')]
    #[Env('SHOPWARE_USAGE_DATA_CONSENT', '')]
    public function testUpdateNewConsentState(string $consent, string $storedValue, bool $shouldExecute): void
    {
        $_SERVER['SHOPWARE_USAGE_DATA_CONSENT'] = $consent;

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('tablesExist')->willReturn(true);
        $this->connection->method('createSchemaManager')->willReturn($schemaManager);
        $this->connection->expects($this->once())->method('fetchAssociative')->willReturn([
            'id' => 1,
            'name' => 'backend_data',
            'state' => $storedValue,
        ]);

        $this->connection->expects($shouldExecute ? $this->once() : $this->never())
            ->method('executeStatement')
            ->with(
                Assert::stringStartsWith('UPDATE `consent_state`'),
                Assert::logicalAnd(
                    Assert::isArray(),
                    Assert::equalTo(['state' => $consent]),
                )
            );

        $event = new PostDeploy(new RunConfiguration(), new NullOutput());
        $this->subscriber->__invoke($event);
    }

    public static function provideForConsentUpdate(): \Generator
    {
        yield 'accepted/accepted' => ['accepted', 'accepted', false];
        yield 'accepted/declined' => ['accepted', 'declined', true];
        yield 'accepted/revoked' => ['accepted', 'revoked', true];
        yield 'revoked/accepted' => ['revoked', 'accepted', true];
        yield 'revoked/declined' => ['revoked', 'declined', false];
        yield 'revoked/revoked' => ['revoked', 'revoked', false];
    }
}
