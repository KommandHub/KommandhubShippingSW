<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Conformance;

use Kommandhub\ShippingSW\Model\Pickup\Pickup;
use Kommandhub\ShippingSW\Model\Pickup\PickupRequest;
use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\Shipment\Label;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEvent;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEventCollection;
use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use Kommandhub\ShippingSW\Provider\Capability;
use Kommandhub\ShippingSW\Provider\Exception\UnsupportedCapabilityException;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * The contract test suite EVERY adapter must pass. A new courier's test class
 * extends this and supplies provider() + context(); it inherits the full set of
 * behavioural guarantees. This is the mechanism that keeps the core provider-
 * agnostic: if an adapter can't satisfy these, it can't be a provider.
 *
 * The suite enforces the two-sided capability contract for each operation:
 *  - supported  -> the method returns the correct canonical type + invariants;
 *  - unsupported -> the method throws UnsupportedCapabilityException.
 */
abstract class ProviderConformanceTestCase extends TestCase
{
    abstract protected function provider(): ShippingProviderInterface;

    abstract protected function context(): ProviderContext;

    public function testKeyIsNonEmptyAndStable(): void
    {
        $provider = $this->provider();

        self::assertNotSame('', $provider->key(), 'A provider key must be non-empty.');
        self::assertSame($provider->key(), $provider->key(), 'A provider key must be stable.');
    }

    public function testSupportsReturnsBoolForEveryCapability(): void
    {
        $provider = $this->provider();

        foreach (Capability::cases() as $capability) {
            self::assertIsBool($provider->supports($capability));
        }
    }

    public function testGetRatesContract(): void
    {
        $provider = $this->provider();
        $request = $this->rateRequest();

        if (!$provider->supports(Capability::GET_RATES)) {
            $this->expectException(UnsupportedCapabilityException::class);
            $provider->getRates($request, $this->context());

            return;
        }

        $quotes = $provider->getRates($request, $this->context());
        self::assertInstanceOf(RateQuoteCollection::class, $quotes);

        foreach ($quotes as $quote) {
            self::assertInstanceOf(RateQuote::class, $quote);
            self::assertSame($provider->key(), $quote->providerKey, 'Every quote must be attributed to the producing provider.');
            self::assertSame($request->currency, $quote->amount->currency, 'Quote currency must match the request currency.');
            self::assertNotSame('', $quote->serviceCode);
        }

        // sortedByPrice() must be monotonically non-decreasing.
        $previous = null;
        foreach ($quotes->sortedByPrice() as $quote) {
            if (null !== $previous) {
                self::assertGreaterThanOrEqual($previous, $quote->amount->minorAmount);
            }
            $previous = $quote->amount->minorAmount;
        }

        if (!$quotes->isEmpty()) {
            self::assertSame($quotes->sortedByPrice()->all()[0], $quotes->cheapest());
        }
    }

    public function testCreateShipmentContractIsIdempotent(): void
    {
        $provider = $this->provider();
        $request = $this->shipmentRequest();

        if (!$provider->supports(Capability::CREATE_SHIPMENT)) {
            $this->expectException(UnsupportedCapabilityException::class);
            $provider->createShipment($request, $this->context());

            return;
        }

        $first = $provider->createShipment($request, $this->context());
        self::assertInstanceOf(Shipment::class, $first);
        self::assertSame($provider->key(), $first->providerKey);
        self::assertNotSame('', $first->providerShipmentId);

        $second = $provider->createShipment($request, $this->context());
        self::assertSame(
            $first->providerShipmentId,
            $second->providerShipmentId,
            'Same idempotencyKey must yield the same shipment, never a second booking.',
        );
    }

    public function testGenerateLabelContract(): void
    {
        $provider = $this->provider();

        if (!$provider->supports(Capability::GENERATE_LABEL)) {
            $this->expectException(UnsupportedCapabilityException::class);
            $provider->generateLabel('any-shipment-id', $this->context());

            return;
        }

        $label = $provider->generateLabel('any-shipment-id', $this->context());
        self::assertInstanceOf(Label::class, $label);
        self::assertTrue(null !== $label->url || null !== $label->base64);
    }

    public function testSchedulePickupContract(): void
    {
        $provider = $this->provider();
        $request = new PickupRequest(
            $provider->key(),
            ['any-shipment-id'],
            $this->originAddress(),
            new \DateTimeImmutable('2026-01-05T10:00:00+00:00'),
        );

        if (!$provider->supports(Capability::SCHEDULE_PICKUP)) {
            $this->expectException(UnsupportedCapabilityException::class);
            $provider->schedulePickup($request, $this->context());

            return;
        }

        $pickup = $provider->schedulePickup($request, $this->context());
        self::assertInstanceOf(Pickup::class, $pickup);
        self::assertSame($provider->key(), $pickup->providerKey);
        self::assertNotSame('', $pickup->providerPickupId);
    }

    public function testTrackContract(): void
    {
        $provider = $this->provider();

        if (!$provider->supports(Capability::TRACK)) {
            $this->expectException(UnsupportedCapabilityException::class);
            $provider->track('ANYTRACK', $this->context());

            return;
        }

        $events = $provider->track('ANYTRACK', $this->context());
        self::assertInstanceOf(TrackingEventCollection::class, $events);

        // sortedByTime() must be chronological.
        $previous = null;
        foreach ($events->sortedByTime() as $event) {
            self::assertInstanceOf(TrackingEvent::class, $event);
            self::assertSame($provider->key(), $event->providerKey);
            self::assertInstanceOf(TrackingStatus::class, $event->status);
            if (null !== $previous) {
                self::assertGreaterThanOrEqual($previous, $event->occurredAt);
            }
            $previous = $event->occurredAt;
        }

        if (!$events->isEmpty()) {
            self::assertSame($events->sortedByTime()->all()[array_key_last($events->sortedByTime()->all())], $events->latest());
        }
    }

    public function testVerifyWebhookRejectsAnInvalidSignature(): void
    {
        $provider = $this->provider();

        if (!$provider->supports(Capability::VERIFY_WEBHOOK)) {
            $this->expectException(UnsupportedCapabilityException::class);
            $provider->verifyWebhook('{"event":"x"}', 'sig', $this->context());

            return;
        }

        // A signature no scheme would ever produce for this body must be rejected.
        $result = $provider->verifyWebhook('{"event":"delivery.update"}', 'obviously-not-valid', $this->context());
        self::assertIsBool($result);
        self::assertFalse($result, 'A forged signature must not verify.');
    }

    protected function originAddress(): Address
    {
        return new Address('NG', 'Lagos', '1 Sample Street', state: 'Lagos', postalCode: '100001', name: 'Merchant', phone: '+2348000000000');
    }

    protected function destinationAddress(): Address
    {
        return new Address('NG', 'Abuja', '2 Buyer Road', state: 'FCT', postalCode: '900001', name: 'Buyer', phone: '+2348111111111');
    }

    protected function rateRequest(): RateRequest
    {
        return new RateRequest(
            $this->originAddress(),
            $this->destinationAddress(),
            Weight::fromKilograms(2.0),
            Dimensions::fromCentimeters(30, 20, 10),
            Currency::NGN,
        );
    }

    protected function shipmentRequest(): ShipmentRequest
    {
        return new ShipmentRequest(
            providerKey: $this->provider()->key(),
            serviceCode: 'standard',
            origin: $this->originAddress(),
            destination: $this->destinationAddress(),
            weight: Weight::fromKilograms(2.0),
            dimensions: Dimensions::fromCentimeters(30, 20, 10),
            reference: 'ORDER-1001',
            idempotencyKey: 'idem-ORDER-1001',
        );
    }
}
