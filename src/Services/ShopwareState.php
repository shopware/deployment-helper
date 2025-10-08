<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;

class ShopwareState
{
    /**
     * @var array<string, string>
     */
    private array $maintenanceMode = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function isInstalled(): bool
    {
        try {
            $this->connection->fetchAllAssociative('SELECT * FROM system_config');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isStorefrontInstalled(): bool
    {
        return InstalledVersions::isInstalled('shopware/storefront');
    }

    public function getPreviousVersion(): string
    {
        try {
            $data = $this->connection->fetchOne('SELECT configuration_value FROM system_config WHERE configuration_key = "deployment.version" AND sales_channel_id IS NULL');
        } catch (\Throwable) {
            return 'unknown';
        }

        if ($data === false) {
            return 'unknown';
        }

        $value = json_decode($data, true, 512, \JSON_THROW_ON_ERROR);

        return $value['_value'];
    }

    public function setVersion(string $version): void
    {
        $id = (string) $this->connection->fetchOne('SELECT id FROM system_config WHERE configuration_key = "deployment.version" AND sales_channel_id IS NULL');
        $payload = json_encode(['_value' => $version], \JSON_THROW_ON_ERROR);

        if ($id !== '') {
            $this->connection->executeStatement('UPDATE system_config SET configuration_value = ? WHERE id = ?', [$payload, $id]);
        } else {
            $this->connection->executeStatement('INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at) VALUES (0x0353f2502acd5dbdfe797c1cc4af9afc, "deployment.version", ?, NULL, NOW())', [$payload]);
        }
    }

    public function disableFirstRunWizard(): void
    {
        $payload = json_encode(['_value' => '2021-01-01 00:00:00'], \JSON_THROW_ON_ERROR);
        $this->connection->executeStatement('INSERT INTO system_config (id, configuration_key, configuration_value, sales_channel_id, created_at) VALUES (0x0353f2502acd5dbdfe797c1cc4af9bfc, "core.frw.completedAt", ?, NULL, NOW())', [$payload]);
    }

    public function getCurrentVersion(): string
    {
        if (InstalledVersions::isInstalled('shopware/platform')) {
            return (string) InstalledVersions::getVersion('shopware/platform');
        }

        return (string) InstalledVersions::getVersion('shopware/core');
    }

    public function isSalesChannelExisting(?string $salesChannelUrl): bool
    {
        return (bool) $this->connection->fetchOne('SELECT id FROM sales_channel_domain WHERE url = ?', [$salesChannelUrl]);
    }

    public function enableMaintenanceMode(): void
    {
        // Make a copy, so we can restore the original state later
        /** @var array<string, string> */
        $data = $this->connection->fetchAllKeyValue('SELECT LOWER(HEX(id)), maintenance FROM sales_channel WHERE type_id = 0x8a243080f92e4c719546314b577cf82b');
        $this->maintenanceMode = $data;

        $this->connection->executeStatement('UPDATE sales_channel SET maintenance = 1 WHERE type_id = 0x8a243080f92e4c719546314b577cf82b');
    }

    public function disableMaintenanceMode(): void
    {
        foreach ($this->maintenanceMode as $id => $maintenance) {
            $this->connection->executeStatement('UPDATE sales_channel SET maintenance = ? WHERE id = UNHEX(?)', [$maintenance, $id]);
        }
    }

    public function getMySqlVersion(): string
    {
        $version = $this->extractMySQLVersion($this->connection->fetchOne('SELECT VERSION()'));

        if (isset($version['mariadb'])) {
            return 'mariadb-' . $version['mariadb'];
        }

        if (isset($version['mysql'])) {
            return 'mysql-' . $version['mysql'];
        }

        return 'unknown';
    }

    /**
     * @return array{mysql?: string, mariadb?: string}
     */
    private function extractMySQLVersion(string $versionString): array
    {
        if (stripos($versionString, 'mariadb') === false) {
            $pos = strpos($versionString, '-');
            if (\is_int($pos)) {
                $versionString = substr($versionString, 0, $pos);
            }

            return ['mysql' => $versionString];
        }

        return ['mariadb' => self::getVersionNumber($versionString)];
    }

    private static function getVersionNumber(string $versionString): string
    {
        $match = preg_match(
            '/^(?:5\.5\.5-)?(mariadb-)?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/i',
            $versionString,
            $versionParts,
        );
        if ($match === 0 || \is_bool($match)) {
            throw new \RuntimeException(\sprintf('Invalid version string: %s', $versionString));
        }

        return $versionParts['major'] . '.' . $versionParts['minor'];
    }
}
