<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Services\ShopwareState;

#[CoversClass(ShopwareState::class)]
class ShopwareStateTest extends TestCase
{
    private Connection&MockObject $connection;
    private ShopwareState $state;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->state = new ShopwareState($this->connection);
    }

    public function testShopwareIsNotInstalled(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willThrowException(new \Exception());
        static::assertFalse($this->state->isInstalled());
    }

    public function testShopwareIsInstalled(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('fetchOne')->willReturn('1');

        static::assertTrue($this->state->isInstalled());
    }

    public function testShopwareIsNotInstalledWhenNoUser(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls('0');

        static::assertFalse($this->state->isInstalled());
    }

    public function testShopwareIsNotInstalledWhenNoSalesChannel(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls('1', '0');

        static::assertFalse($this->state->isInstalled());
    }

    public function testStorefrontInstalled(): void
    {
        static::assertFalse($this->state->isStorefrontInstalled());

        $before = $this->getBefore();

        InstalledVersions::reload([
            'root' => $before['root'],
            'versions' => [
                'shopware/storefront' => [
                    'version' => '1.0.0',
                    'dev_requirement' => false,
                ],
            ],
        ]);

        static::assertTrue($this->state->isStorefrontInstalled());

        InstalledVersions::reload($before);
    }

    public function testGetPreviousVersionTableDoesNotExistYet(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willThrowException(new \Exception());
        static::assertSame('unknown', $this->state->getPreviousVersion());
    }

    public function testGetPreviousVersionNotExisting(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);
        static::assertSame('unknown', $this->state->getPreviousVersion());
    }

    public function testGetPreviousVersion(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn('{"_value": "v1.0.0"}');

        static::assertSame('v1.0.0', $this->state->getPreviousVersion());
    }

    public function testSetVersion(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn('id');

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('UPDATE system_config SET configuration_value = ? WHERE id = ?', ['{"_value":"v1.0.0"}', 'id']);

        $this->state->setVersion('v1.0.0');
    }

    public function testDisableFRW(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at) VALUES (0x0353f2502acd5dbdfe797c1cc4af9bfc, "core.frw.completedAt", ?, NULL, NOW())', ['{"_value":"2021-01-01 00:00:00"}']);

        $this->state->disableFirstRunWizard();
    }

    public function testSetVersionInsert(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at) VALUES (0x0353f2502acd5dbdfe797c1cc4af9afc, "deployment.version", ?, NULL, NOW())', ['{"_value":"v1.0.0"}']);

        $this->state->setVersion('v1.0.0');
    }

    public function testGetCurrentVersion(): void
    {
        $before = $this->getBefore();

        InstalledVersions::reload([
            'root' => $before['root'],
            'versions' => [
                'shopware/platform' => [
                    'version' => '1.0.0',
                    'dev_requirement' => false,
                ],
            ],
        ]);

        static::assertSame('1.0.0', $this->state->getCurrentVersion());

        InstalledVersions::reload($before);
    }

    public function testGetCurrentVersionFromCore(): void
    {
        $before = $this->getBefore();

        InstalledVersions::reload([
            'root' => $before['root'],
            'versions' => [
                'shopware/core' => [
                    'version' => '2.0.0',
                    'dev_requirement' => false,
                ],
            ],
        ]);

        static::assertSame('2.0.0', $this->state->getCurrentVersion());

        InstalledVersions::reload($before);
    }

    public function testIsSalesChannelExisting(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn('id');

        static::assertTrue($this->state->isSalesChannelExisting('http://localhost'));
    }

    public function testIsSalesChannelNotExisting(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        static::assertFalse($this->state->isSalesChannelExisting('http://localhost'));
    }

    /**
     * @return array{root: array{name: string, pretty_version: string, version: string, reference: string|null, type: string, install_path: string, aliases: string[], dev: bool}, versions: array<string, array{pretty_version?: string, version?: string, reference?: string|null, type?: string, install_path?: string, aliases?: string[], dev_requirement: bool, replaced?: string[], provided?: string[]}>}
     */
    private function getBefore(): array
    {
        return InstalledVersions::getAllRawData()[0];
    }

    public function testEnableMaintenanceMode(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with(
                'SELECT LOWER(HEX(id)), maintenance FROM sales_channel WHERE type_id = UNHEX(?)',
                ['8a243080f92e4c719546314b577cf82b'],
            )
            ->willReturn(['id' => 0]);

        // No persisted snapshot yet: both the snapshot lookup and the lookup of an
        // existing system_config row miss
        $this->connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturn(false);

        $statements = [];
        $this->connection
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 1;
            });

        $this->state->enableMaintenanceMode();

        // The snapshot is persisted before maintenance is enabled, so a failed run
        // leaves it behind for the retry
        static::assertSame(
            [
                [
                    'INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at) VALUES (UNHEX(REPLACE(UUID(), "-", "")), ?, ?, NULL, NOW())',
                    ['deployment.maintenanceMode', '{"_value":{"id":0}}'],
                ],
                [
                    'UPDATE sales_channel SET maintenance = 1 WHERE type_id = UNHEX(?)',
                    ['8a243080f92e4c719546314b577cf82b'],
                ],
            ],
            $statements,
        );
    }

    public function testEnableMaintenanceModeReusesSnapshotOfFailedRun(): void
    {
        // A previous run enabled maintenance and failed before restoring it: the
        // channel is still in maintenance, the snapshot holds the original flag
        $this->connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn(['id' => 1]);

        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn('{"_value":{"id":0}}');

        $statements = [];
        $this->connection
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 1;
            });

        $this->state->enableMaintenanceMode();

        // The snapshot of the failed run is reused, only maintenance is enabled again
        $enableStatements = $statements;
        static::assertSame(
            [
                [
                    'UPDATE sales_channel SET maintenance = 1 WHERE type_id = UNHEX(?)',
                    ['8a243080f92e4c719546314b577cf82b'],
                ],
            ],
            $enableStatements,
        );

        static::assertSame(0, $this->state->disableMaintenanceMode());

        // The state from before the first run is restored and the snapshot is deleted
        static::assertSame(
            [
                [
                    'UPDATE sales_channel SET maintenance = 1 WHERE type_id = UNHEX(?)',
                    ['8a243080f92e4c719546314b577cf82b'],
                ],
                [
                    'UPDATE sales_channel SET maintenance = ? WHERE id = UNHEX(?)',
                    [0, 'id'],
                ],
                [
                    'DELETE FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL',
                    ['deployment.maintenanceMode'],
                ],
            ],
            $statements,
        );
    }

    public function testEnableMaintenanceModeExtendsSnapshotWithNewSalesChannels(): void
    {
        // channel-b was created after a failed run had taken its snapshot
        $this->connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn(['channel-a' => 1, 'channel-b' => 0]);

        $this->connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql): string {
                if (str_contains($sql, 'configuration_value')) {
                    return '{"_value":{"channel-a":0}}';
                }

                return 'existing-row-id';
            });

        $statements = [];
        $this->connection
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 1;
            });

        $this->state->enableMaintenanceMode();

        static::assertSame(
            [
                [
                    'UPDATE system_config SET configuration_value = ? WHERE id = ?',
                    ['{"_value":{"channel-a":0,"channel-b":0}}', 'existing-row-id'],
                ],
                [
                    'UPDATE sales_channel SET maintenance = 1 WHERE type_id = UNHEX(?)',
                    ['8a243080f92e4c719546314b577cf82b'],
                ],
            ],
            $statements,
        );
    }

    public function testEnableMaintenanceModeTakesFreshSnapshotWhenPersistedOneIsCorrupt(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn(['id' => 1]);

        $this->connection
            ->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql): string {
                if (str_contains($sql, 'configuration_value')) {
                    return 'not-json';
                }

                return 'existing-row-id';
            });

        $statements = [];
        $this->connection
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 1;
            });

        $this->state->enableMaintenanceMode();

        // The corrupt row is overwritten with a fresh snapshot of the current state
        static::assertSame(
            [
                [
                    'UPDATE system_config SET configuration_value = ? WHERE id = ?',
                    ['{"_value":{"id":1}}', 'existing-row-id'],
                ],
                [
                    'UPDATE sales_channel SET maintenance = 1 WHERE type_id = UNHEX(?)',
                    ['8a243080f92e4c719546314b577cf82b'],
                ],
            ],
            $statements,
        );
    }

    public function testDisableMaintenanceMode(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn(['id' => 0]);

        $this->connection
            ->method('fetchOne')
            ->willReturn(false);

        $this->state->enableMaintenanceMode();

        $statements = [];
        $this->connection
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 1;
            });

        static::assertSame(0, $this->state->disableMaintenanceMode());

        static::assertSame(
            [
                ['UPDATE sales_channel SET maintenance = ? WHERE id = UNHEX(?)', [0, 'id']],
                ['DELETE FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['deployment.maintenanceMode']],
            ],
            $statements,
        );
    }

    public function testDisableMaintenanceModeReturnsChannelsRemainingInMaintenance(): void
    {
        // channel-a was already in maintenance before the update and must stay enabled
        $this->connection
            ->method('fetchAllKeyValue')
            ->willReturn(['channel-a' => 1, 'channel-b' => 0]);

        $this->connection
            ->method('fetchOne')
            ->willReturn(false);

        $this->state->enableMaintenanceMode();

        static::assertSame(1, $this->state->disableMaintenanceMode());
    }

    public function testDisableMaintenanceModeRestoresPersistedSnapshot(): void
    {
        // No enableMaintenanceMode() in this process: the snapshot persisted by a
        // failed run is restored and deleted
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn('{"_value":{"id":0}}');

        $statements = [];
        $this->connection
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 1;
            });

        static::assertSame(0, $this->state->disableMaintenanceMode());

        static::assertSame(
            [
                ['UPDATE sales_channel SET maintenance = ? WHERE id = UNHEX(?)', [0, 'id']],
                ['DELETE FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['deployment.maintenanceMode']],
            ],
            $statements,
        );
    }

    #[DataProvider('mysqlVersionProvider')]
    public function testGetMySqlVersion(string $versionString, string $expectedResult): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT VERSION()')
            ->willReturn($versionString);

        static::assertSame($expectedResult, $this->state->getMySqlVersion());
    }

    /**
     * @return array<string, array{versionString: string, expectedResult: string}>
     */
    public static function mysqlVersionProvider(): array
    {
        return [
            'MySQL 8.0' => [
                'versionString' => '8.0.23',
                'expectedResult' => 'mysql-8.0',
            ],
            'MySQL 5.7 with Ubuntu suffix' => [
                'versionString' => '5.7.33-0ubuntu0.18.04.1',
                'expectedResult' => 'mysql-5.7',
            ],
            'MariaDB 10.5' => [
                'versionString' => '10.5.9-MariaDB',
                'expectedResult' => 'mariadb-10.5',
            ],
            'MariaDB with complex version string' => [
                'versionString' => '5.5.5-10.6.7-MariaDB-1:10.6.7+maria~focal',
                'expectedResult' => 'mariadb-10.6',
            ],
            'MariaDB alternative format' => [
                'versionString' => 'mariadb-10.11.2',
                'expectedResult' => 'mariadb-10.11',
            ],
            'MariaDB uppercase' => [
                'versionString' => '10.3.31-MARIADB-0ubuntu0.20.04.1',
                'expectedResult' => 'mariadb-10.3',
            ],
            'Percona Server' => [
                'versionString' => '8.0.25-15',
                'expectedResult' => 'mysql-8.0',
            ],
        ];
    }

    public function testGetMySqlVersionWithInvalidMariaDBVersion(): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT VERSION()')
            ->willReturn('mariadb-invalid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid version string: mariadb-invalid');

        $this->state->getMySqlVersion();
    }
}
