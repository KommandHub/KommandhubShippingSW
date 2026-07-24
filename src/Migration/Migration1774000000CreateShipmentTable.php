<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Creates the shipment store. idempotency_key is UNIQUE so a retried
 * create-shipment job can never insert a duplicate booking at the database
 * level, backing the contract-level idempotency guarantee.
 */
class Migration1774000000CreateShipmentTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kommandhub_shipping_shipment` (
                `id`                    BINARY(16)   NOT NULL,
                `order_id`              BINARY(16)   NULL,
                `sales_channel_id`      BINARY(16)   NULL,
                `provider_key`          VARCHAR(64)  NOT NULL,
                `provider_shipment_id`  VARCHAR(255) NOT NULL,
                `service_code`          VARCHAR(64)  NULL,
                `status`                VARCHAR(32)  NOT NULL,
                `tracking_number`       VARCHAR(128) NULL,
                `tracking_url`          LONGTEXT     NULL,
                `reference`             VARCHAR(64)  NULL,
                `idempotency_key`       VARCHAR(128) NOT NULL,
                `created_at`            DATETIME(3)  NOT NULL,
                `updated_at`            DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.kh_shipment.idempotency_key` (`idempotency_key`),
                KEY `idx.kh_shipment.order_id` (`order_id`),
                KEY `idx.kh_shipment.provider_shipment` (`provider_key`, `provider_shipment_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
