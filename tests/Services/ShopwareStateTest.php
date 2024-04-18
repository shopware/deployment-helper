<?php declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Deployment\Services\ShopwareState;
use PHPUnit\Framework\TestCase;

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
        $this->connection
            ->expects(static::once())
            ->method('fetchAllAssociative')
            ->willReturn([]);
        static::assertTrue($this->state->isInstalled());
    }

    public function testStorefrontInstalled(): void
    {
        static::assertFalse($this->state->isStorefrontInstalled());

        $before = InstalledVersions::getAllRawData();

        InstalledVersions::reload([
            'versions' => [
                'shopware/storefront' => [
                    'version' => '1.0.0',
                ],
            ],
        ]);

        static::assertTrue($this->state->isStorefrontInstalled());

        InstalledVersions::reload($before);
    }

    public function testGetPreviousVersionNotExisting(): void
    {
        $this->connection
            ->expects(static::once())
            ->method('fetchOne')
            ->willReturn(false);
        static::assertSame('unknown', $this->state->getPreviousVersion());
    }

    public function testGetPreviousVersion(): void
    {
        $this->connection
            ->expects(static::once())
            ->method('fetchOne')
            ->willReturn('{"_value": "v1.0.0"}');

        static::assertSame('v1.0.0', $this->state->getPreviousVersion());
    }

    public function testSetVersion(): void
    {
        $this->connection
            ->expects(static::once())
            ->method('fetchOne')
            ->willReturn('id');

        $this->connection
            ->expects(static::once())
            ->method('executeStatement')
            ->with('UPDATE system_config SET configuration_value = ? WHERE id = ?', ['{"_value":"v1.0.0"}', 'id']);

        $this->state->setVersion('v1.0.0');
    }

    public function testDisableFRW(): void
    {
        $this->connection
            ->expects(static::once())
            ->method('executeStatement')
            ->with('INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at) VALUES (0x0353f2502acd5dbdfe797c1cc4af9bfc, "core.frw.completedAt", ?, NULL, NOW())', ['{"_value":"2021-01-01 00:00:00"}']);

        $this->state->disableFirstRunWizard();
    }

    public function testSetVersionInsert(): void
    {
        $this->connection
            ->expects(static::once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->connection
            ->expects(static::once())
            ->method('executeStatement')
            ->with('INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at) VALUES (0x0353f2502acd5dbdfe797c1cc4af9afc, "deployment.version", ?, NULL, NOW())', ['{"_value":"v1.0.0"}']);

        $this->state->setVersion('v1.0.0');
    }

    public function testGetCurrentVersion(): void
    {
        $before = InstalledVersions::getAllRawData();

        InstalledVersions::reload([
            'versions' => [
                'shopware/platform' => [
                    'version' => '1.0.0',
                ],
            ],
        ]);

        static::assertSame('1.0.0', $this->state->getCurrentVersion());

        InstalledVersions::reload($before);
    }

    public function testGetCurrentVersionFromCore(): void
    {
        $before = InstalledVersions::getAllRawData();

        InstalledVersions::reload([
            'versions' => [
                'shopware/core' => [
                    'version' => '2.0.0',
                ],
            ],
        ]);

        static::assertSame('2.0.0', $this->state->getCurrentVersion());

        InstalledVersions::reload($before);
    }
}
