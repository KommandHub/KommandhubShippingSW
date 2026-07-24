<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Tracking;

/**
 * A single parcel scan/status change, normalised. Both webhook pushes and
 * scheduled polls produce these, so the pipeline that drives the Shopware
 * delivery state machine has one input shape regardless of source.
 */
final readonly class TrackingEvent
{
    public function __construct(
        public string $providerKey,
        public string $trackingNumber,
        public TrackingStatus $status,
        public \DateTimeImmutable $occurredAt,
        public ?string $description = null,
        public ?string $location = null,
    ) {
    }
}
