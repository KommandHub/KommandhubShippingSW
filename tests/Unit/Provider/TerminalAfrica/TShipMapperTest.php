<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider\TerminalAfrica;

use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TShipMapper;
use PHPUnit\Framework\TestCase;

/**
 * Mapping tests against recorded TShip fixtures — the boundary where raw
 * provider payloads become canonical model. Everything downstream trusts these.
 */
final class TShipMapperTest extends TestCase
{
    private TShipMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TShipMapper();
    }

    private function fixture(string $name): array
    {
        return json_decode(
            (string) file_get_contents(\dirname(__DIR__, 3) . '/Fixtures/terminal/' . $name . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
    }

    public function testRatesAreNormalisedToCanonicalQuotes(): void
    {
        $quotes = $this->mapper->toRateQuotes($this->fixture('rates_response'), Currency::NGN);

        self::assertCount(2, $quotes);
        foreach ($quotes as $quote) {
            self::assertSame('terminal_africa', $quote->providerKey);
            self::assertSame(Currency::NGN, $quote->amount->currency);
        }

        // Amounts arrive as major-unit strings; stored as minor units.
        $cheapest = $quotes->cheapest();
        self::assertNotNull($cheapest);
        self::assertSame('rate_gig_std', $cheapest->serviceCode);
        self::assertSame(250000, $cheapest->amount->minorAmount);
        self::assertSame('GIG Logistics', $cheapest->carrierName);
        self::assertSame(2, $cheapest->estimatedDaysMin);

        $express = $quotes->sortedByPrice()->all()[1];
        self::assertSame(780050, $express->amount->minorAmount);

        // carrierCode is populated for allow-list filtering.
        self::assertSame('gig', $cheapest->carrierCode);
        self::assertSame('dhl', $express->carrierCode);
    }

    public function testCarriersMapToCanonicalCatalogue(): void
    {
        $carriers = $this->mapper->toCarriers($this->fixture('carriers_response'));

        self::assertCount(3, $carriers);
        self::assertSame(['gig', 'dhl', 'ups'], $carriers->codes());
        self::assertTrue($carriers->has('dhl'));
    }

    public function testAddressAndParcelIdsAreParsed(): void
    {
        self::assertSame('AD-77298486083', $this->mapper->addressId($this->fixture('address_response')));
        self::assertSame('PC-09284697565', $this->mapper->parcelId($this->fixture('parcel_response')));
    }

    public function testArrangedShipmentMapsStatusAndTracking(): void
    {
        // /shipments/pickup returns the confirmed shipment.
        $shipment = $this->mapper->toShipment($this->fixture('pickup_response'), 'rate_gig_std');

        self::assertSame('SH-40208776515', $shipment->providerShipmentId);
        self::assertSame('terminal_africa', $shipment->providerKey);
        self::assertSame(TrackingStatus::CREATED, $shipment->status); // "confirmed" -> CREATED
        self::assertSame('TRK-88817263', $shipment->trackingNumber);
        self::assertSame('rate_gig_std', $shipment->serviceCode);
    }

    public function testLabelMapsToPdfUrl(): void
    {
        $label = $this->mapper->toLabel($this->fixture('label_response'));

        self::assertStringEndsWith('.pdf', (string) $label->url);
        self::assertSame('pdf', $label->format->value);
    }

    public function testTrackingEventsAreCanonicalAndSortable(): void
    {
        $events = $this->mapper->toTrackingEvents($this->fixture('track_response'), 'TA-TRK-001');

        self::assertCount(3, $events);

        $latest = $events->latest();
        self::assertNotNull($latest);
        self::assertSame(TrackingStatus::DELIVERED, $latest->status);
        self::assertSame('terminal_africa', $latest->providerKey);

        // Oldest first after sorting.
        self::assertSame(TrackingStatus::CREATED, $events->sortedByTime()->all()[0]->status);
    }

    public function testStatusVocabularyIsMapped(): void
    {
        self::assertSame(TrackingStatus::IN_TRANSIT, $this->mapper->mapStatus('in-transit'));
        self::assertSame(TrackingStatus::IN_TRANSIT, $this->mapper->mapStatus('picked_up'));
        self::assertSame(TrackingStatus::OUT_FOR_DELIVERY, $this->mapper->mapStatus('out for delivery'));
        self::assertSame(TrackingStatus::DELIVERED, $this->mapper->mapStatus('DELIVERED'));
        self::assertSame(TrackingStatus::EXCEPTION, $this->mapper->mapStatus('delivery-failed'));
        self::assertSame(TrackingStatus::UNKNOWN, $this->mapper->mapStatus('teleported'));
    }
}
