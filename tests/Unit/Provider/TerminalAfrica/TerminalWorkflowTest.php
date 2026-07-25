<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider\TerminalAfrica;

use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use Kommandhub\ShippingSW\Provider\Reference\InMemoryProviderReferenceStore;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TerminalAfricaAdapter;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TShipMapper;
use Kommandhub\ShippingSW\Tests\Support\StubTerminalTransport;
use PHPUnit\Framework\TestCase;

/**
 * Proves the adapter runs Terminal's documented ID-based workflow and reuses
 * address/parcel ids instead of recreating them — the core of the fix.
 */
final class TerminalWorkflowTest extends TestCase
{
    private function adapter(StubTerminalTransport $transport, InMemoryProviderReferenceStore $store): TerminalAfricaAdapter
    {
        return new TerminalAfricaAdapter($transport, new TShipMapper(), $store);
    }

    private function context(array $extra = []): ProviderContext
    {
        return new ProviderContext(apiKey: 'k', sandbox: true, webhookSecret: 'w', extra: $extra);
    }

    private function rateRequest(): RateRequest
    {
        return new RateRequest(
            new Address('NG', 'Lagos', 'Warehouse Rd', postalCode: '100001'),
            new Address('NG', 'Abuja', 'Buyer St', postalCode: '900001'),
            Weight::fromKilograms(2.0),
            Dimensions::fromCentimeters(30, 20, 10),
            Currency::NGN,
        );
    }

    public function testGetRatesCreatesAddressesAndParcelThenRates(): void
    {
        $transport = new StubTerminalTransport();
        $adapter = $this->adapter($transport, new InMemoryProviderReferenceStore());

        $quotes = $adapter->getRates($this->rateRequest(), $this->context());

        // Documented order: 2 addresses (pickup + delivery), 1 parcel, then rates.
        self::assertSame(
            ['/addresses', '/addresses', '/parcels', '/rates/multi/shipment'],
            $transport->postedPaths(),
        );
        self::assertSame('rate_gig_std', $quotes->cheapest()?->serviceCode);
    }

    public function testAddressAndParcelIdsAreReusedAcrossCalls(): void
    {
        $transport = new StubTerminalTransport();
        $adapter = $this->adapter($transport, new InMemoryProviderReferenceStore());

        $adapter->getRates($this->rateRequest(), $this->context());
        $adapter->getRates($this->rateRequest(), $this->context()); // identical inputs

        $posts = $transport->postedPaths();
        // Addresses + parcel created ONCE; only the rates call repeats.
        self::assertSame(2, \count(array_filter($posts, static fn (string $p): bool => '/addresses' === $p)));
        self::assertSame(1, \count(array_filter($posts, static fn (string $p): bool => '/parcels' === $p)));
        self::assertSame(2, \count(array_filter($posts, static fn (string $p): bool => '/rates/multi/shipment' === $p)));
    }

    public function testConfiguredPickupAddressIsNotRecreated(): void
    {
        $transport = new StubTerminalTransport();
        $adapter = $this->adapter($transport, new InMemoryProviderReferenceStore());

        $adapter->getRates($this->rateRequest(), $this->context([
            TerminalAfricaAdapter::EXTRA_PICKUP_ADDRESS_ID => 'AD-CONFIGURED',
        ]));

        // Only the delivery address is created; the warehouse id comes from config.
        self::assertSame(1, \count(array_filter($transport->postedPaths(), static fn (string $p): bool => '/addresses' === $p)));
    }

    public function testCreateShipmentArrangesByRateId(): void
    {
        $transport = new StubTerminalTransport();
        $adapter = $this->adapter($transport, new InMemoryProviderReferenceStore());

        $shipment = $adapter->createShipment(new ShipmentRequest(
            providerKey: 'terminal_africa',
            serviceCode: 'rate_gig_std',
            origin: new Address('NG', 'Lagos', 'Warehouse Rd'),
            destination: new Address('NG', 'Abuja', 'Buyer St'),
            weight: Weight::fromKilograms(2.0),
            dimensions: Dimensions::fromCentimeters(30, 20, 10),
            reference: 'ORDER-1',
            idempotencyKey: 'idem-1',
        ), $this->context());

        self::assertSame(['/shipments/pickup'], $transport->postedPaths());
        self::assertSame('SH-40208776515', $shipment->providerShipmentId);
        self::assertSame('rate_gig_std', $shipment->serviceCode);
    }

    public function testTrackUsesShipmentId(): void
    {
        $transport = new StubTerminalTransport();
        $adapter = $this->adapter($transport, new InMemoryProviderReferenceStore());

        $shipment = new Shipment(
            providerKey: 'terminal_africa',
            providerShipmentId: 'SH-40208776515',
            serviceCode: 'rate_gig_std',
            status: TrackingStatus::CREATED,
            trackingNumber: 'TRK-88817263',
        );

        $events = $adapter->track($shipment, $this->context());

        self::assertGreaterThan(0, $events->count());
        // Tracked by shipment_id; events stamped with the linkable tracking number.
        self::assertSame('/shipments/track/SH-40208776515', $transport->calls[0]['path']);
        self::assertSame('TRK-88817263', $events->latest()?->trackingNumber);
    }
}
