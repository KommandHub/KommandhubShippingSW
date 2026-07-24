<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider;

use Kommandhub\ShippingSW\Model\Pickup\Pickup;
use Kommandhub\ShippingSW\Model\Pickup\PickupRequest;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\Shipment\Label;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEventCollection;
use Kommandhub\ShippingSW\Provider\Exception\ProviderException;
use Kommandhub\ShippingSW\Provider\Exception\UnsupportedCapabilityException;

/**
 * The single contract every courier integration implements. It speaks only the
 * canonical model (Model\**): no provider ever exposes its raw payloads through
 * this interface. Adding a courier means adding one class that implements this
 * and passes the shared conformance suite — nothing in the core changes.
 *
 * Contract for the capability methods (getRates, createShipment, generateLabel,
 * schedulePickup, track, verifyWebhook):
 *  - If supports() returns true for the matching Capability, the method behaves.
 *  - If it returns false, the method MUST throw UnsupportedCapabilityException.
 * That equivalence is enforced by the conformance suite.
 */
interface ShippingProviderInterface
{
    /**
     * Stable configuration key, e.g. "terminal_africa". Unique across providers
     * and used to select the adapter from per-sales-channel config.
     */
    public function key(): string;

    public function supports(Capability $capability): bool;

    /**
     * @throws UnsupportedCapabilityException
     * @throws ProviderException on transport/mapping failure
     */
    public function getRates(RateRequest $request, ProviderContext $context): RateQuoteCollection;

    /**
     * Book a shipment. MUST be idempotent on $request->idempotencyKey: a repeat
     * call with the same key returns the same shipment, never a second booking.
     *
     * @throws UnsupportedCapabilityException
     * @throws ProviderException
     */
    public function createShipment(ShipmentRequest $request, ProviderContext $context): Shipment;

    /**
     * @throws UnsupportedCapabilityException
     * @throws ProviderException
     */
    public function generateLabel(string $providerShipmentId, ProviderContext $context): Label;

    /**
     * @throws UnsupportedCapabilityException
     * @throws ProviderException
     */
    public function schedulePickup(PickupRequest $request, ProviderContext $context): Pickup;

    /**
     * @throws UnsupportedCapabilityException
     * @throws ProviderException
     */
    public function track(string $trackingNumber, ProviderContext $context): TrackingEventCollection;

    /**
     * Verify a webhook's authenticity from its raw (unparsed) body and the
     * signature the provider sent. Implementations must use a constant-time
     * comparison. Returns false on any mismatch rather than throwing.
     *
     * @throws UnsupportedCapabilityException
     */
    public function verifyWebhook(string $rawBody, string $signature, ProviderContext $context): bool;
}
