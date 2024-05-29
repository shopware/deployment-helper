<?php

declare(strict_types=1);

namespace Shopware\Deployment\DependencyInjection;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Shopware\Deployment\Helper\EnvironmentHelper;

/**
 * @codeCoverageIgnore
 */
readonly class MySQLFactory
{
    private static function create(): Connection
    {
        $config = new Configuration();

        $url = (string) EnvironmentHelper::getVariable('DATABASE_URL');
        if ($url === '') {
            throw new \RuntimeException('$DATABASE_URL is not set');
        }

        $parameters = [
            'url' => $url,
            'charset' => 'utf8mb4',
            'driver' => 'pdo_mysql',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
                \PDO::ATTR_TIMEOUT => 1,
            ],
        ];

        if (class_exists(DsnParser::class)) {
            unset($parameters['url']);
            $dsnParser = new DsnParser(['mysql' => 'pdo_mysql']);
            $parameters = [...$parameters, ...$dsnParser->parse($url)];
        }

        $sslCa = EnvironmentHelper::getVariable('DATABASE_SSL_CA');
        if (\is_string($sslCa) && $sslCa !== '') {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        }

        $sslCert = EnvironmentHelper::getVariable('DATABASE_SSL_CERT');
        if (\is_string($sslCert) && $sslCert !== '') {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CERT] = $sslCert;
        }

        $sslCertKey = EnvironmentHelper::getVariable('DATABASE_SSL_KEY');
        if (\is_string($sslCertKey) && $sslCertKey !== '') {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_KEY] = $sslCertKey;
        }

        if (EnvironmentHelper::hasVariable('DATABASE_SSL_DONT_VERIFY_SERVER_CERT')) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        return DriverManager::getConnection($parameters, $config);
    }

    public static function createAndRetry(int $retries = 10): Connection
    {
        $retries = max(1, $retries);

        for ($i = 0; $i < $retries; ++$i) {
            $con = null;

            try {
                $con = self::create();
                $con->fetchAllAssociative('SELECT 1');

                return $con;
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'Unknown database') && $con instanceof Connection) {
                    return $con;
                }

                if ($i === $retries - 1) {
                    throw $e;
                }

                sleep(1);
            }
        }

        throw new \RuntimeException('Could not connect to database');
    }
}
