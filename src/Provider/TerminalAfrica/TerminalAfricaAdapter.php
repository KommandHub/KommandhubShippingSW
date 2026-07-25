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
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Provider\Capability;
use Kommandhub\ShippingSW\Provider\Exception\UnsupportedCapabilityException;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\Reference\ProviderReferenceStore;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;

/**
 * Terminal Africa (TShip) adapter for Nigeria. TShip is ID-based, so this
 * adapter runs the documented multi-step workflow internally — the core only
 * ever calls getRates()/createShipment()/track() and never sees an AD-/PC-/SH- id:
 *
 *   getRates      → resolve pickup + delivery address ids, resolve a parcel id,
 *                   then POST /rates/multi/shipment with those ids.
 *   createShipment→ POST /shipments/pickup {rate_id} (the rate carries the
 *                   address/parcel context server-side, so nothing is recreated).
 *   track         → GET /shipments/track/{shipment_id}.
 *
 * Address and parcel ids are cached in a ProviderReferenceStore keyed by content
 * hash, so the constant checkout recalculations reuse the warehouse address and
 * an identical parcel instead of POSTing a new one each time.
 *
 * Capability difference: Terminal arranges collection as part of
 * /shipments/pickup, so there is no standalone SCHEDULE_PICKUP.
 */
final class TerminalAfricaAdapter implements ShippingProviderInterface
{
    private const WEBHOOK_ALGO = 'sha512';

    private const ADDRESS_NS = 'terminal_address';
    private const PARCEL_NS = 'terminal_parcel';
    private const ADDRESS_TTL = 86400; // addresses are stable; cache a day
    private const PARCEL_TTL = 3600;

    /** Provider-config keys carried on ProviderContext::$extra. */
    public const EXTRA_PICKUP_ADDRESS_ID = 'terminalPickupAddressId';
    public const EXTRA_PACKAGING_ID = 'terminalPackagingId';

    private const CAPABILITIES = [
        Capability::GET_RATES,
        Capability::CREATE_SHIPMENT,
        Capability::GENERATE_LABEL,
        Capability::TRACK,
        Capability::VERIFY_WEBHOOK,
        Capability::LIST_CARRIERS,
    ];

    public function __construct(
        private readonly TerminalTransport $transport,
        private readonly TShipMapper $mapper,
        private readonly ProviderReferenceStore $references,
    ) {
    }

    public function key(): string
    {
        return TShipMapper::PROVIDER_KEY;
    }

    public function supports(Capability $capability): bool
    {
        return \in_array($capability, self::CAPABILITIES, true);
    }

    public function getRates(RateRequest $request, ProviderContext $context): RateQuoteCollection
    {
        $pickupAddressId = $this->pickupAddressId($request->origin, $context);
        $deliveryAddressId = $this->resolveAddressId($request->destination, $context);
        $parcelId = $this->resolveParcelId($request, $context);

        $response = $this->transport->request(
            'POST',
            '/rates/multi/shipment',
            $this->mapper->ratesForShipmentPayload($pickupAddressId, $deliveryAddressId, [$parcelId], $request->currency),
            $context,
        );

        return $this->mapper->toRateQuotes($response, $request->currency);
    }

    public function listCarriers(ProviderContext $context): CarrierCollection
    {
        $response = $this->transport->request('GET', '/carriers', [], $context);

        return $this->mapper->toCarriers($response);
    }

    public function createShipment(ShipmentRequest $request, ProviderContext $context): Shipment
    {
        // rate_id (== our serviceCode) is arranged into a shipment; the addresses
        // and parcel created during rating are reused server-side via that id.
        $response = $this->transport->request(
            'POST',
            '/shipments/pickup',
            $this->mapper->arrangePayload($request->serviceCode, $request->reference, $request->idempotencyKey),
            $context,
        );

        return $this->mapper->toShipment($response, $request->serviceCode);
    }

    public function generateLabel(string $providerShipmentId, ProviderContext $context): Label
    {
        $response = $this->transport->request('GET', '/shipments/' . rawurlencode($providerShipmentId), [], $context);

        return $this->mapper->toLabel($response);
    }

    public function schedulePickup(PickupRequest $request, ProviderContext $context): Pickup
    {
        // Collection is arranged inside createShipment (/shipments/pickup).
        throw UnsupportedCapabilityException::for($this->key(), Capability::SCHEDULE_PICKUP);
    }

    public function track(Shipment $shipment, ProviderContext $context): TrackingEventCollection
    {
        // TShip tracks by shipment_id (not the carrier tracking number).
        $response = $this->transport->request(
            'GET',
            '/shipments/track/' . rawurlencode($shipment->providerShipmentId),
            [],
            $context,
        );

        // Stamp events with the linkable tracking number so the pipeline can
        // match them back to the stored shipment.
        return $this->mapper->toTrackingEvents($response, $shipment->trackingNumber ?? $shipment->providerShipmentId);
    }

    public function verifyWebhook(string $rawBody, string $signature, ProviderContext $context): bool
    {
        $secret = $context->webhookSecret;
        if (null === $secret || '' === $secret || '' === $signature) {
            return false;
        }

        return hash_equals(hash_hmac(self::WEBHOOK_ALGO, $rawBody, $secret), $signature);
    }

    /**
     * The pickup (warehouse) address: prefer a merchant-configured, pre-created
     * Terminal address id — zero creation, zero duplication. Otherwise create
     * and cache one from the origin.
     */
    private function pickupAddressId(Address $origin, ProviderContext $context): string
    {
        $configured = $context->extra[self::EXTRA_PICKUP_ADDRESS_ID] ?? null;
        if (\is_string($configured) && '' !== $configured) {
            return $configured;
        }

        return $this->resolveAddressId($origin, $context);
    }

    private function resolveAddressId(Address $address, ProviderContext $context): string
    {
        $hash = $this->mapper->addressHash($address);
        $cached = $this->references->get(self::ADDRESS_NS, $hash);
        if (null !== $cached) {
            return $cached;
        }

        $response = $this->transport->request('POST', '/addresses', $this->mapper->addressCreatePayload($address), $context);
        $id = $this->mapper->addressId($response);
        $this->references->save(self::ADDRESS_NS, $hash, $id, self::ADDRESS_TTL);

        return $id;
    }

    private function resolveParcelId(RateRequest $request, ProviderContext $context): string
    {
        $packagingId = $context->extra[self::EXTRA_PACKAGING_ID] ?? null;
        $packagingId = \is_string($packagingId) ? $packagingId : null;

        $hash = $this->mapper->parcelHash($request, $packagingId);
        $cached = $this->references->get(self::PARCEL_NS, $hash);
        if (null !== $cached) {
            return $cached;
        }

        $response = $this->transport->request('POST', '/parcels', $this->mapper->parcelCreatePayload($request, $packagingId), $context);
        $id = $this->mapper->parcelId($response);
        $this->references->save(self::PARCEL_NS, $hash, $id, self::PARCEL_TTL);

        return $id;
    }
}
