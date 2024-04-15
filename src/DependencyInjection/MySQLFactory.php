<?php declare(strict_types=1);

namespace Shopware\Deployment\DependencyInjection;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Shopware\Deployment\Helper\EnvironmentHelper;

class MySQLFactory
{
    private static function create(): Connection
    {
        $config = new Configuration();

        $url = (string) EnvironmentHelper::getVariable('DATABASE_URL');
        if ('' === $url) {
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

        var_dump($parameters);

        if ($sslCa = EnvironmentHelper::getVariable('DATABASE_SSL_CA')) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        }

        if ($sslCert = EnvironmentHelper::getVariable('DATABASE_SSL_CERT')) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CERT] = $sslCert;
        }

        if ($sslCertKey = EnvironmentHelper::getVariable('DATABASE_SSL_KEY')) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_KEY] = $sslCertKey;
        }

        if (EnvironmentHelper::getVariable('DATABASE_SSL_DONT_VERIFY_SERVER_CERT')) {
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
                var_dump($e->getMessage());
                if (str_contains($e->getMessage(), 'Unknown database') && $con) {
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
