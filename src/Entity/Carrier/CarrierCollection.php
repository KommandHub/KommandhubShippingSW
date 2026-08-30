<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Entity\Carrier;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CarrierEntity>
 */
class CarrierCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CarrierEntity::class;
    }
}
