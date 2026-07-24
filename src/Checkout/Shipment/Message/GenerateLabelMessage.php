<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Shipment\Message;

/**
 * Async command to fetch and store a shipment's waybill. Keyed by the plugin's
 * own shipment entity id (not the provider's), so a retry re-fetches the label
 * for the same record.
 */
final readonly class GenerateLabelMessage
{
    public function __construct(public string $shipmentId)
    {
    }
}
