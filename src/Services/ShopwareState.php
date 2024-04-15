<?php declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;

class ShopwareState
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function isInstalled(): bool
    {
        try {
            $this->connection->fetchAllAssociative('SELECT * FROM system_config');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isStorefrontInstalled(): bool
    {
        return InstalledVersions::isInstalled('shopware/storefront');
    }

    public function getPreviousVersion(): string
    {
        $data = $this->connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = "deployment.version" AND sales_channel_id IS NULL');

        if ($data === false) {
            return 'unknown';
        }

        $value = json_decode($data, true, JSON_THROW_ON_ERROR);

        return $value['_value'];
    }

    public function setVersion(string $version): void
    {
        $id = $this->connection->fetchOne('SELECT id FROM system_config WHERE configuration_key = "deployment.version" AND sales_channel_id IS NULL');
        $payload = json_encode(['_value' => $version], JSON_THROW_ON_ERROR);

        if ($id) {
            $this->connection->executeStatement('UPDATE system_config SET configuration_value = ? WHERE id = ?', [$payload, $id]);
        } else {
            $this->connection->executeStatement('INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at) VALUES (0x0353f2502acd5dbdfe797c1cc4af9afc, "deployment.version", ?, NULL, NOW())', [$payload]);
        }
    }

    public function getCurrentVersion(): string
    {
        if (InstalledVersions::isInstalled('shopware/platform')) {
            return (string) InstalledVersions::getVersion('shopware/platform');
        }

        return (string) InstalledVersions::getVersion('shopware/core');
    }
}
