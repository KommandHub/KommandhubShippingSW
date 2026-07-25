<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\BobGo;

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
use Kommandhub\ShippingSW\Provider\Exception\UnsupportedCapabilityException;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;

/**
 * Bob Go courier aggregator for South Africa (ZAR). Implements the exact same
 * contract as Terminal — no core, checkout, admin or pipeline code changed to
 * add it. Its differences are expressed purely as a Capability flag: Bob Go
 * arranges collection as part of shipment creation, so it does NOT support
 * SCHEDULE_PICKUP, and schedulePickup() throws accordingly. Nothing in the core
 * branches on "bobgo"; it only ever asks supports().
 */
final class BobGoAdapter implements ShippingProviderInterface
{
    private const WEBHOOK_ALGO = 'sha256'; // Bob Go signs with SHA-256; Terminal used SHA-512.

    /** Capabilities Bob Go offers — note SCHEDULE_PICKUP is absent. */
    private const CAPABILITIES = [
        Capability::GET_RATES,
        Capability::CREATE_SHIPMENT,
        Capability::GENERATE_LABEL,
        Capability::TRACK,
        Capability::VERIFY_WEBHOOK,
        Capability::LIST_CARRIERS,
    ];

    public function __construct(
        private readonly BobGoTransport $transport,
        private readonly BobGoMapper $mapper,
    ) {
    }

    public function key(): string
    {
        return BobGoMapper::PROVIDER_KEY;
    }

    public function supports(Capability $capability): bool
    {
        return \in_array($capability, self::CAPABILITIES, true);
    }

    public function getRates(RateRequest $request, ProviderContext $context): RateQuoteCollection
    {
        $response = $this->transport->request('POST', '/rates', $this->mapper->ratesRequestPayload($request), $context);

        return $this->mapper->toRateQuotes($response, $request->currency);
    }

    public function listCarriers(ProviderContext $context): CarrierCollection
    {
        $response = $this->transport->request('GET', '/providers', [], $context);

        return $this->mapper->toCarriers($response);
    }

    public function createShipment(ShipmentRequest $request, ProviderContext $context): Shipment
    {
        $response = $this->transport->request('POST', '/shipments', $this->mapper->shipmentRequestPayload($request), $context);

        return $this->mapper->toShipment($response, $request->serviceCode);
    }

    public function generateLabel(string $providerShipmentId, ProviderContext $context): Label
    {
        $response = $this->transport->request('GET', '/shipments/' . rawurlencode($providerShipmentId), [], $context);

        return $this->mapper->toLabel($response);
    }

    public function schedulePickup(PickupRequest $request, ProviderContext $context): Pickup
    {
        // Bob Go collects as part of shipment creation — there is no standalone
        // pickup to schedule. Fail loudly rather than pretend.
        throw UnsupportedCapabilityException::for($this->key(), Capability::SCHEDULE_PICKUP);
    }

    public function track(Shipment $shipment, ProviderContext $context): TrackingEventCollection
    {
        // Bob Go tracks by its tracking_reference (the shipment's tracking number).
        $trackingNumber = $shipment->trackingNumber ?? $shipment->providerShipmentId;
        $response = $this->transport->request('GET', '/tracking/' . rawurlencode($trackingNumber), [], $context);

        return $this->mapper->toTrackingEvents($response, $trackingNumber);
    }

    public function verifyWebhook(string $rawBody, string $signature, ProviderContext $context): bool
    {
        $secret = $context->webhookSecret;
        if (null === $secret || '' === $secret || '' === $signature) {
            return false;
        }

        return hash_equals(hash_hmac(self::WEBHOOK_ALGO, $rawBody, $secret), $signature);
    }
}
