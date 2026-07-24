<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Shipment\Message;

use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;

/**
 * Async command to book a shipment. Write-side operations run off the request
 * cycle so checkout/admin never block on a courier API. Carries the canonical
 * ShipmentRequest (which holds the idempotencyKey) plus the ids needed to link
 * the result back to the order.
 */
final readonly class CreateShipmentMessage
{
    public function __construct(
        public ShipmentRequest $request,
        public ?string $orderId = null,
        public ?string $salesChannelId = null,
    ) {
    }
}
