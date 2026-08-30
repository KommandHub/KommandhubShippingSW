<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Prefetched carrier catalogue, one row per (provider, carrier). The UNIQUE
 * (provider_key, code) lets the synchroniser upsert idempotently.
 */
class Migration1774000002CreateCarrierTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774000002;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kommandhub_shipping_carrier` (
                `id`           BINARY(16)   NOT NULL,
                `provider_key` VARCHAR(64)  NOT NULL,
                `code`         VARCHAR(128) NOT NULL,
                `name`         VARCHAR(255) NOT NULL,
                `logo_url`     LONGTEXT     NULL,
                `active`       TINYINT(1)   NOT NULL DEFAULT 1,
                `provider_data` JSON        NULL,
                `created_at`   DATETIME(3)  NOT NULL,
                `updated_at`   DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.kh_carrier.provider_code` (`provider_key`, `code`),
                KEY `idx.kh_carrier.provider` (`provider_key`, `active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
