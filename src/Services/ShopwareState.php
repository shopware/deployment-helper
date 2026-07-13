<?php

declare(strict_types=1);

namespace Shopware\Deployment\Services;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;

class ShopwareState
{
    /**
     * Shopware's SalesChannelDefinition::TYPE_STOREFRONT UUID (hex, no dashes).
     * Bound via UNHEX(?) for prepared statements, or inlined as 0x… in raw SQL.
     */
    private const STOREFRONT_TYPE_ID = '8a243080f92e4c719546314b577cf82b';

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

            $numberOfExistingUsers = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `user`');
            if ($numberOfExistingUsers === 0) {
                return false;
            }

            $salesChannelExists = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `sales_channel`');
            if ($salesChannelExists === 0) {
                return false;
            }

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

    /**
     * Returns active storefront sales channels with their assigned theme
     * (lowercased hex IDs). Mirrors the selection used by
     * `theme:compile --active-only` / DatabaseAvailableThemeProvider.
     *
     * When a sales channel has multiple theme assignments, the first row wins
     * (same practical shape as Shopware's key-value theme provider).
     *
     * @return list<array{salesChannelId: string, themeId: string}>
     */
    public function getActiveStorefrontThemeAssignments(): array
    {
        /** @var list<array{sales_channel_id: string, theme_id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(sc.id)) AS sales_channel_id, LOWER(HEX(tsc.theme_id)) AS theme_id
             FROM sales_channel sc
             INNER JOIN theme_sales_channel tsc ON tsc.sales_channel_id = sc.id
             WHERE sc.active = 1 AND sc.type_id = UNHEX(?)',
            [self::STOREFRONT_TYPE_ID],
        );

        $assignments = [];
        $seenSalesChannels = [];
        foreach ($rows as $row) {
            if (isset($seenSalesChannels[$row['sales_channel_id']])) {
                continue;
            }

            $seenSalesChannels[$row['sales_channel_id']] = true;
            $assignments[] = [
                'salesChannelId' => $row['sales_channel_id'],
                'themeId' => $row['theme_id'],
            ];
        }

        return $assignments;
    }

    public function enableMaintenanceMode(): void
    {
        // Make a copy, so we can restore the original state later
        /** @var array<string, string> */
        $data = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), maintenance FROM sales_channel WHERE type_id = UNHEX(?)',
            [self::STOREFRONT_TYPE_ID],
        );
        $this->maintenanceMode = $data;

        $this->connection->executeStatement(
            'UPDATE sales_channel SET maintenance = 1 WHERE type_id = UNHEX(?)',
            [self::STOREFRONT_TYPE_ID],
        );
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

            return ['mysql' => self::getVersionNumber($versionString)];
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
