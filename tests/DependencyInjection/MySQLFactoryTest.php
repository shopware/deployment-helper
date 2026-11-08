<?php

declare(strict_types=1);

namespace Shopware\Deployment\Tests\DependencyInjection;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Deployment\DependencyInjection\MySQLFactory;

#[CoversClass(MySQLFactory::class)]
class MySQLFactoryTest extends TestCase
{
    #[BackupGlobals(true)]
    public function testIdleTimeoutIsConfiguredForEveryConnection(): void
    {
        $_SERVER['DATABASE_URL'] = 'mysql://user:password@localhost/shopware';

        $connection = (new \ReflectionMethod(MySQLFactory::class, 'create'))->invoke(null);

        static::assertInstanceOf(Connection::class, $connection);
        static::assertFalse($connection->isConnected());
        // Inspect the lazy connection's options without opening a real database connection.
        $parameters = $connection->getParams(); // @phpstan-ignore method.internal
        static::assertArrayHasKey('driverOptions', $parameters);
        static::assertSame(
            'SET SESSION wait_timeout = CAST(GREATEST(@@SESSION.wait_timeout, 86400) AS UNSIGNED)',
            $parameters['driverOptions'][\PHP_VERSION_ID >= 80400 ? \Pdo\Mysql::ATTR_INIT_COMMAND : \PDO::MYSQL_ATTR_INIT_COMMAND],
        );
    }
}
