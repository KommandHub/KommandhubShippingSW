<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Support;

use Kommandhub\ShippingSW\Model\Carrier\CarrierCollection;
use Kommandhub\ShippingSW\Model\Pickup\Pickup;
use Kommandhub\ShippingSW\Model\Pickup\PickupRequest;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\Shipment\Label;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEventCollection;
use Kommandhub\ShippingSW\Provider\Capability;
use Kommandhub\ShippingSW\Provider\Exception\ProviderException;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;

/**
 * A provider whose getRates always fails — models an outage/open circuit so the
 * aggregator's flat-rate fallback path can be exercised.
 */
final class FailingProvider implements ShippingProviderInterface
{
    public function __construct(private readonly string $key = 'terminal_africa')
    {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function supports(Capability $capability): bool
    {
        return true;
    }

    public function getRates(RateRequest $request, ProviderContext $context): RateQuoteCollection
    {
        throw new ProviderException('simulated outage');
    }

    public function listCarriers(ProviderContext $context): CarrierCollection
    {
        throw new ProviderException('simulated outage');
    }

    public function createShipment(ShipmentRequest $request, ProviderContext $context): Shipment
    {
        throw new ProviderException('unused');
    }

    public function generateLabel(string $providerShipmentId, ProviderContext $context): Label
    {
        throw new ProviderException('unused');
    }

    public function schedulePickup(PickupRequest $request, ProviderContext $context): Pickup
    {
        throw new ProviderException('unused');
    }

    public function track(Shipment $shipment, ProviderContext $context): TrackingEventCollection
    {
        throw new ProviderException('unused');
    }

    public function verifyWebhook(string $rawBody, string $signature, ProviderContext $context): bool
    {
        return false;
    }
}
