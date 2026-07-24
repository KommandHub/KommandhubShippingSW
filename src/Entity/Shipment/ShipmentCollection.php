<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Entity\Shipment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ShipmentEntity>
 */
class ShipmentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ShipmentEntity::class;
    }
}
