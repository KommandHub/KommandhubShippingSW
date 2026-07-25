<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider;

use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEvent;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEventCollection;
use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use Kommandhub\ShippingSW\Provider\BobGo\BobGoAdapter;
use Kommandhub\ShippingSW\Provider\BobGo\BobGoMapper;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\Reference\InMemoryProviderReferenceStore;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TerminalAfricaAdapter;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TShipMapper;
use Kommandhub\ShippingSW\Tests\Support\StubBobGoTransport;
use Kommandhub\ShippingSW\Tests\Support\StubTerminalTransport;
use PHPUnit\Framework\TestCase;

/**
 * The Phase 2 regression proof: Terminal (NG/NGN) and Bob Go (SA/ZAR) run side
 * by side and return IDENTICALLY-SHAPED canonical output for equivalent inputs.
 * Every assertion here is provider-neutral — it never mentions "terminal" or
 * "bobgo" — which is exactly why one codebase and one contract serve both.
 */
final class CrossProviderConsistencyTest extends TestCase
{
    /**
     * @return array<string, array{ShippingProviderInterface, Currency, Address, string}>
     */
    public static function providers(): array
    {
        return [
            'terminal (NG)' => [
                new TerminalAfricaAdapter(new StubTerminalTransport(), new TShipMapper(), new InMemoryProviderReferenceStore()),
                Currency::NGN,
                new Address('NG', 'Lagos', '1 Sample Street', postalCode: '100001'),
                'standard',
            ],
            'bobgo (SA)' => [
                new BobGoAdapter(new StubBobGoTransport(), new BobGoMapper()),
                Currency::ZAR,
                new Address('ZA', 'Cape Town', '1 Long Street', postalCode: '8001'),
                'ECO',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providers')]
    public function testRatesHaveIdenticalCanonicalShape(
        ShippingProviderInterface $provider,
        Currency $currency,
        Address $address,
        string $serviceCode,
    ): void {
        $quotes = $provider->getRates($this->rateRequest($currency, $address), $this->context());

        self::assertInstanceOf(RateQuoteCollection::class, $quotes);
        self::assertGreaterThan(0, $quotes->count());
        foreach ($quotes as $quote) {
            self::assertInstanceOf(RateQuote::class, $quote);
            self::assertSame($provider->key(), $quote->providerKey);
            self::assertInstanceOf(Money::class, $quote->amount);
            self::assertSame($currency, $quote->amount->currency);
            self::assertNotSame('', $quote->serviceCode);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providers')]
    public function testShipmentsHaveIdenticalCanonicalShape(
        ShippingProviderInterface $provider,
        Currency $currency,
        Address $address,
        string $serviceCode,
    ): void {
        $shipment = $provider->createShipment($this->shipmentRequest($provider->key(), $address, $serviceCode), $this->context());

        self::assertInstanceOf(Shipment::class, $shipment);
        self::assertSame($provider->key(), $shipment->providerKey);
        self::assertNotSame('', $shipment->providerShipmentId);
        self::assertInstanceOf(TrackingStatus::class, $shipment->status);
        self::assertSame($serviceCode, $shipment->serviceCode);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providers')]
    public function testTrackingHasIdenticalCanonicalShape(
        ShippingProviderInterface $provider,
        Currency $currency,
        Address $address,
        string $serviceCode,
    ): void {
        $shipment = new Shipment(
            providerKey: $provider->key(),
            providerShipmentId: 'SH-1',
            serviceCode: $serviceCode,
            status: TrackingStatus::CREATED,
            trackingNumber: 'TRK-1',
        );
        $events = $provider->track($shipment, $this->context());

        self::assertInstanceOf(TrackingEventCollection::class, $events);
        foreach ($events as $event) {
            self::assertInstanceOf(TrackingEvent::class, $event);
            self::assertSame($provider->key(), $event->providerKey);
            self::assertInstanceOf(TrackingStatus::class, $event->status);
        }
        // Both providers' latest event is DELIVERED in the fixtures — same
        // canonical status vocabulary regardless of the provider's own tokens.
        self::assertSame(TrackingStatus::DELIVERED, $events->latest()?->status);
    }

    private function context(): ProviderContext
    {
        return new ProviderContext(apiKey: 'k', sandbox: true, webhookSecret: 'secret');
    }

    private function rateRequest(Currency $currency, Address $address): RateRequest
    {
        return new RateRequest($address, $address, Weight::fromKilograms(2.0), Dimensions::fromCentimeters(30, 20, 10), $currency);
    }

    private function shipmentRequest(string $providerKey, Address $address, string $serviceCode): ShipmentRequest
    {
        return new ShipmentRequest(
            providerKey: $providerKey,
            serviceCode: $serviceCode,
            origin: $address,
            destination: $address,
            weight: Weight::fromKilograms(2.0),
            dimensions: Dimensions::fromCentimeters(30, 20, 10),
            reference: 'ORDER-1',
            idempotencyKey: 'idem-1',
        );
    }
}
