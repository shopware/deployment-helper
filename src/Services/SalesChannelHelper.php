<?php

namespace Shopware\Deployment\Services;

use Doctrine\DBAL\Connection;

class SalesChannelHelper
{
    public static function removeExistingHeadless(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM sales_channel WHERE type_id = 0xf183ee5650cf4bdb8a774337575067a6');
    }
}
