<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Pickup;

use Kommandhub\ShippingSW\Model\ValueObject\Address;

/**
 * A request to have a courier collect one or more booked shipments from a
 * location within a ready/close time window.
 */
final readonly class PickupRequest
{
    /**
     * @param list<string> $providerShipmentIds
     */
    public function __construct(
        public string $providerKey,
        public array $providerShipmentIds,
        public Address $location,
        public \DateTimeImmutable $readyAt,
        public ?\DateTimeImmutable $closeBy = null,
    ) {
    }
}
