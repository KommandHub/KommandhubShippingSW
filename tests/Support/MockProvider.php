<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Support;

use Kommandhub\ShippingSW\Model\Carrier\Carrier;
use Kommandhub\ShippingSW\Model\Carrier\CarrierCollection;
use Kommandhub\ShippingSW\Model\Pickup\Pickup;
use Kommandhub\ShippingSW\Model\Pickup\PickupRequest;
use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\Shipment\Label;
use Kommandhub\ShippingSW\Model\Shipment\LabelFormat;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEvent;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEventCollection;
use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use Kommandhub\ShippingSW\Provider\Capability;
use Kommandhub\ShippingSW\Provider\Exception\UnsupportedCapabilityException;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;

/**
 * A fully-canonical in-memory provider used to exercise the contract and the
 * conformance suite without any network. It deliberately supports every
 * capability and produces deterministic, canonical output; per-key idempotency
 * for createShipment is implemented here to prove the contract is satisfiable.
 *
 * A constructor flag lets tests spin up a provider that supports only a subset
 * of capabilities, so the "unsupported capability throws" half of the contract
 * is testable too.
 */
final class MockProvider implements ShippingProviderInterface
{
    /** @var array<string, Shipment> keyed by idempotency key */
    private array $bookings = [];

    /**
     * @param list<Capability> $capabilities defaults to all
     */
    public function __construct(
        private readonly string $key = 'mock',
        private readonly array $capabilities = [
            Capability::GET_RATES,
            Capability::CREATE_SHIPMENT,
            Capability::GENERATE_LABEL,
            Capability::SCHEDULE_PICKUP,
            Capability::TRACK,
            Capability::VERIFY_WEBHOOK,
            Capability::LIST_CARRIERS,
        ],
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function supports(Capability $capability): bool
    {
        return \in_array($capability, $this->capabilities, true);
    }

    public function getRates(RateRequest $request, ProviderContext $context): RateQuoteCollection
    {
        $this->guard(Capability::GET_RATES);

        $base = 150000 + $request->weight->grams; // minor units, deterministic

        return new RateQuoteCollection(
            new RateQuote($this->key, 'express', 'Mock Express', Money::fromMinor($base + 50000, $request->currency), 1, 2, 'Mock Express Co', 'mock_express'),
            new RateQuote($this->key, 'standard', 'Mock Standard', Money::fromMinor($base, $request->currency), 3, 5, 'Mock Standard Co', 'mock_standard'),
        );
    }

    public function listCarriers(ProviderContext $context): CarrierCollection
    {
        $this->guard(Capability::LIST_CARRIERS);

        return new CarrierCollection(
            new Carrier('mock_express', 'Mock Express Co'),
            new Carrier('mock_standard', 'Mock Standard Co'),
        );
    }

    public function createShipment(ShipmentRequest $request, ProviderContext $context): Shipment
    {
        $this->guard(Capability::CREATE_SHIPMENT);

        // Idempotency: a repeat idempotencyKey returns the identical shipment.
        if (isset($this->bookings[$request->idempotencyKey])) {
            return $this->bookings[$request->idempotencyKey];
        }

        $id = 'mock_ship_' . substr(hash('sha256', $request->idempotencyKey), 0, 12);
        $tracking = 'MOCK' . strtoupper(substr(hash('sha256', $request->idempotencyKey), 0, 10));

        $shipment = new Shipment(
            providerKey: $this->key,
            providerShipmentId: $id,
            serviceCode: $request->serviceCode,
            status: TrackingStatus::CREATED,
            trackingNumber: $tracking,
            trackingUrl: 'https://track.mock.test/' . $tracking,
            label: null,
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );

        return $this->bookings[$request->idempotencyKey] = $shipment;
    }

    public function generateLabel(string $providerShipmentId, ProviderContext $context): Label
    {
        $this->guard(Capability::GENERATE_LABEL);

        return new Label(
            LabelFormat::PDF,
            base64: base64_encode('%PDF-1.4 mock waybill ' . $providerShipmentId),
        );
    }

    public function schedulePickup(PickupRequest $request, ProviderContext $context): Pickup
    {
        $this->guard(Capability::SCHEDULE_PICKUP);

        return new Pickup(
            providerKey: $this->key,
            providerPickupId: 'mock_pickup_' . substr(hash('sha256', implode(',', $request->providerShipmentIds)), 0, 10),
            scheduledAt: $request->readyAt,
            status: 'scheduled',
        );
    }

    public function track(Shipment $shipment, ProviderContext $context): TrackingEventCollection
    {
        $this->guard(Capability::TRACK);

        $trackingNumber = $shipment->trackingNumber ?? $shipment->providerShipmentId;

        // Intentionally returned newest-first to prove the collection sorts.
        return new TrackingEventCollection(
            new TrackingEvent($this->key, $trackingNumber, TrackingStatus::IN_TRANSIT, new \DateTimeImmutable('2026-01-02T09:00:00+00:00'), 'Departed facility', 'Lagos'),
            new TrackingEvent($this->key, $trackingNumber, TrackingStatus::CREATED, new \DateTimeImmutable('2026-01-01T09:00:00+00:00'), 'Shipment created', 'Lagos'),
        );
    }

    public function verifyWebhook(string $rawBody, string $signature, ProviderContext $context): bool
    {
        $this->guard(Capability::VERIFY_WEBHOOK);

        $expected = hash_hmac('sha256', $rawBody, (string) $context->webhookSecret);

        return hash_equals($expected, $signature);
    }

    /** Convenience for tests: the signature a valid webhook would carry. */
    public static function sign(string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $rawBody, $secret);
    }

    private function guard(Capability $capability): void
    {
        if (!$this->supports($capability)) {
            throw UnsupportedCapabilityException::for($this->key, $capability);
        }
    }
}
