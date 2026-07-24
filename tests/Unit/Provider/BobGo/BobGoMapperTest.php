<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider\BobGo;

use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Provider\BobGo\BobGoMapper;
use PHPUnit\Framework\TestCase;

final class BobGoMapperTest extends TestCase
{
    private BobGoMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new BobGoMapper();
    }

    private function fixture(string $name): array
    {
        return json_decode(
            (string) file_get_contents(\dirname(__DIR__, 3) . '/Fixtures/bobgo/' . $name . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
    }

    public function testRatesNormaliseToCanonicalQuotes(): void
    {
        $quotes = $this->mapper->toRateQuotes($this->fixture('rates_response'), Currency::ZAR);

        self::assertCount(2, $quotes);
        $cheapest = $quotes->cheapest();
        self::assertNotNull($cheapest);
        self::assertSame('bobgo', $cheapest->providerKey);
        self::assertSame('ECO', $cheapest->serviceCode);
        self::assertSame(8900, $cheapest->amount->minorAmount);
        self::assertSame(Currency::ZAR, $cheapest->amount->currency);
        self::assertSame('The Courier Guy', $cheapest->carrierName);
    }

    public function testShipmentMapsStatusAndTracking(): void
    {
        $shipment = $this->mapper->toShipment($this->fixture('create_shipment_response'), 'ECO');

        self::assertSame('bg_ship_55', $shipment->providerShipmentId);
        self::assertSame(TrackingStatus::CREATED, $shipment->status); // "collected" -> CREATED
        self::assertSame('BG-REF-77', $shipment->trackingNumber);
    }

    public function testLabelMapsToPdfUrl(): void
    {
        $label = $this->mapper->toLabel($this->fixture('label_response'));

        self::assertStringEndsWith('.pdf', (string) $label->url);
    }

    public function testCheckpointsMapToCanonicalEvents(): void
    {
        $events = $this->mapper->toTrackingEvents($this->fixture('track_response'), 'BG-REF-77');

        self::assertCount(3, $events);
        $latest = $events->latest();
        self::assertNotNull($latest);
        self::assertSame(TrackingStatus::DELIVERED, $latest->status);
        self::assertSame(TrackingStatus::CREATED, $events->sortedByTime()->all()[0]->status); // collected
    }

    public function testStatusVocabularyIsMapped(): void
    {
        self::assertSame(TrackingStatus::IN_TRANSIT, $this->mapper->mapStatus('in-transit'));
        self::assertSame(TrackingStatus::RETURNED, $this->mapper->mapStatus('return-to-sender'));
        self::assertSame(TrackingStatus::EXCEPTION, $this->mapper->mapStatus('failed-delivery'));
        self::assertSame(TrackingStatus::UNKNOWN, $this->mapper->mapStatus('who-knows'));
    }
}
