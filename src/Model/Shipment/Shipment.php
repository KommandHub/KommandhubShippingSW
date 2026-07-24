<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Shipment;

use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;

/**
 * A booked shipment as the core understands it. providerShipmentId is the
 * provider's own identifier, used for label/track/pickup follow-ups. The label
 * may be absent at creation and generated separately.
 */
final readonly class Shipment
{
    public function __construct(
        public string $providerKey,
        public string $providerShipmentId,
        public string $serviceCode,
        public TrackingStatus $status,
        public ?string $trackingNumber = null,
        public ?string $trackingUrl = null,
        public ?Label $label = null,
        public ?\DateTimeImmutable $createdAt = null,
    ) {
    }
}
