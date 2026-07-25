<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\TerminalAfrica;

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
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;

/**
 * Terminal Africa (TShip) courier aggregator for Nigeria. All payload knowledge
 * is delegated to TShipMapper and all I/O to TerminalTransport, so this class is
 * pure orchestration and the whole thing is testable against fixtures.
 *
 * Terminal supports every capability, so no method throws
 * UnsupportedCapabilityException — but supports() is still authoritative and the
 * conformance suite verifies the mapping either way.
 */
final class TerminalAfricaAdapter implements ShippingProviderInterface
{
    private const WEBHOOK_ALGO = 'sha512';

    public function __construct(
        private readonly TerminalTransport $transport,
        private readonly TShipMapper $mapper,
    ) {
    }

    public function key(): string
    {
        return TShipMapper::PROVIDER_KEY;
    }

    public function supports(Capability $capability): bool
    {
        // Terminal covers the full surface.
        return true;
    }

    public function getRates(RateRequest $request, ProviderContext $context): RateQuoteCollection
    {
        $response = $this->transport->request('POST', '/rates/shipment', $this->mapper->ratesRequestPayload($request), $context);

        return $this->mapper->toRateQuotes($response, $request->currency);
    }

    public function listCarriers(ProviderContext $context): CarrierCollection
    {
        $response = $this->transport->request('GET', '/carriers', [], $context);

        return $this->mapper->toCarriers($response);
    }

    public function createShipment(ShipmentRequest $request, ProviderContext $context): Shipment
    {
        $response = $this->transport->request('POST', '/shipments', $this->mapper->shipmentRequestPayload($request), $context);

        return $this->mapper->toShipment($response, $request->serviceCode);
    }

    public function generateLabel(string $providerShipmentId, ProviderContext $context): Label
    {
        $response = $this->transport->request('GET', '/shipments/' . rawurlencode($providerShipmentId) . '/label', [], $context);

        return $this->mapper->toLabel($response);
    }

    public function schedulePickup(PickupRequest $request, ProviderContext $context): Pickup
    {
        $response = $this->transport->request('POST', '/shipments/pickup', $this->mapper->pickupRequestPayload($request), $context);

        return $this->mapper->toPickup($response);
    }

    public function track(string $trackingNumber, ProviderContext $context): TrackingEventCollection
    {
        $response = $this->transport->request('GET', '/shipments/track/' . rawurlencode($trackingNumber), [], $context);

        return $this->mapper->toTrackingEvents($response, $trackingNumber);
    }

    public function verifyWebhook(string $rawBody, string $signature, ProviderContext $context): bool
    {
        $secret = $context->webhookSecret;
        if (null === $secret || '' === $secret || '' === $signature) {
            return false;
        }

        $expected = hash_hmac(self::WEBHOOK_ALGO, $rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
