<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\Services\SystemConfigHelper;

#[CoversClass(SystemConfigHelper::class)]
class SystemConfigHelperTest extends TestCase
{
    public function testGet(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT configuration_value FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['key'])
            ->willReturn('{"_value": "value"}');

        $systemConfigHelper = new SystemConfigHelper($connection);

        static::assertSame('value', $systemConfigHelper->get('key'));
    }

    public function testGetInt(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT configuration_value FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['key'])
            ->willReturn('{"_value": 5}');

        $systemConfigHelper = new SystemConfigHelper($connection);

        static::assertSame('5', $systemConfigHelper->get('key'));
    }

    public function testGetArray(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT configuration_value FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['key'])
            ->willReturn('{"_value": {"key": "value"}}');

        $systemConfigHelper = new SystemConfigHelper($connection);

        static::expectException(\UnexpectedValueException::class);
        $systemConfigHelper->get('key');
    }

    public function testGetNull(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT configuration_value FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['key'])
            ->willReturn(false);

        $systemConfigHelper = new SystemConfigHelper($connection);

        static::assertNull($systemConfigHelper->get('key'));
    }

    public function testSetNotExistingKey(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT id FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['key'])
            ->willReturn('5');

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with('UPDATE system_config SET configuration_value = ? WHERE id = ?');

        $systemConfigHelper = new SystemConfigHelper($connection);
        $systemConfigHelper->set('key', 'value');
    }

    public function testSetExistingKey(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT id FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', ['key'])
            ->willReturn('');

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with('INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at) VALUES (?, ?, ?, NULL, NOW())');

        $systemConfigHelper = new SystemConfigHelper($connection);
        $systemConfigHelper->set('key', 'value');
    }
}
