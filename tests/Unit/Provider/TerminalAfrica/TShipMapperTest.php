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
    }

    public function testShipmentResponseMapsStatusAndTracking(): void
    {
        $shipment = $this->mapper->toShipment($this->fixture('create_shipment_response'), 'rate_gig_std');

        self::assertSame('ship_abc123', $shipment->providerShipmentId);
        self::assertSame('terminal_africa', $shipment->providerKey);
        self::assertSame(TrackingStatus::CREATED, $shipment->status); // "confirmed" -> CREATED
        self::assertSame('TA-TRK-001', $shipment->trackingNumber);
        self::assertSame('rate_gig_std', $shipment->serviceCode);
    }

    public function testLabelMapsToPdfUrl(): void
    {
        $label = $this->mapper->toLabel($this->fixture('label_response'));

        self::assertStringEndsWith('.pdf', (string) $label->url);
        self::assertSame('pdf', $label->format->value);
    }

    public function testPickupResponseMaps(): void
    {
        $pickup = $this->mapper->toPickup($this->fixture('pickup_response'));

        self::assertSame('pick_789', $pickup->providerPickupId);
        self::assertSame('scheduled', $pickup->status);
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
