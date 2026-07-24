<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Creates the tracking-event log, one row per parcel scan, cascading from its
 * shipment so removing a shipment cleans up its history.
 */
class Migration1774000001CreateTrackingEventTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1774000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kommandhub_shipping_tracking_event` (
                `id`               BINARY(16)   NOT NULL,
                `shipment_id`      BINARY(16)   NOT NULL,
                `provider_key`     VARCHAR(64)  NOT NULL,
                `tracking_number`  VARCHAR(128) NULL,
                `status`           VARCHAR(32)  NOT NULL,
                `occurred_at`      DATETIME(3)  NOT NULL,
                `description`      LONGTEXT     NULL,
                `location`         VARCHAR(255) NULL,
                `created_at`       DATETIME(3)  NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.kh_track.tracking_number` (`tracking_number`),
                CONSTRAINT `fk.kh_track.shipment_id`
                    FOREIGN KEY (`shipment_id`)
                    REFERENCES `kommandhub_shipping_shipment` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
