<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Shipment;

use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;

/**
 * The instruction to book a shipment with a provider for a chosen service.
 *
 * idempotencyKey makes create-shipment safe to retry: the async job carries it,
 * and a provider (or our own dedup) must return the same shipment for a repeat
 * key rather than booking twice. reference is the merchant-side order number.
 */
final readonly class ShipmentRequest
{
    public function __construct(
        public string $providerKey,
        public string $serviceCode,
        public Address $origin,
        public Address $destination,
        public Weight $weight,
        public Dimensions $dimensions,
        public string $reference,
        public string $idempotencyKey,
        public ?Money $declaredValue = null,
    ) {
    }
}
