<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Entity\TrackingEvent;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ShipmentTrackingEventEntity>
 */
class ShipmentTrackingEventCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ShipmentTrackingEventEntity::class;
    }
}
