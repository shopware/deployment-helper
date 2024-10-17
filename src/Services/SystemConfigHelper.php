<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Doctrine\DBAL\Connection;

class SystemConfigHelper
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function get(string $key): ?string
    {
        $data = $this->connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', [$key]);

        if ($data === false) {
            return null;
        }

        $value = json_decode($data, true, 512, \JSON_THROW_ON_ERROR)['_value'];

        if (\is_array($value)) {
            throw new \UnexpectedValueException('Expected string, got array');
        }

        return (string) $value;
    }

    public function set(string $key, string $value): void
    {
        $id = (string) $this->connection->fetchOne('SELECT id FROM system_config WHERE configuration_key = ? AND sales_channel_id IS NULL', [$key]);
        $payload = json_encode(['_value' => $value], \JSON_THROW_ON_ERROR);

        if ($id !== '') {
            $this->connection->executeStatement('UPDATE system_config SET configuration_value = ? WHERE id = ?', [$payload, $id]);
        } else {
            $this->connection->executeStatement('INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at) VALUES (?, ?, ?, NULL, NOW())', [random_bytes(16), $key, $payload]);
        }
    }
}
