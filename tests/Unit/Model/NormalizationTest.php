<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Model;

use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\Shipment\Label;
use Kommandhub\ShippingSW\Model\Shipment\LabelFormat;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEvent;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEventCollection;
use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use PHPUnit\Framework\TestCase;

/**
 * Normalisation guarantees for the canonical value objects — the invariants the
 * adapters rely on when they map raw provider payloads inward.
 */
final class NormalizationTest extends TestCase
{
    public function testWeightCanonicalisesToGrams(): void
    {
        self::assertSame(2000, Weight::fromKilograms(2.0)->grams);
        self::assertSame(2.0, Weight::fromGrams(2000)->kilograms());
    }

    public function testNegativeWeightRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Weight::fromGrams(-1);
    }

    public function testDimensionsVolumetricWeight(): void
    {
        // 30x20x10 cm = 6000 cm³; /5000 * 1000 = 1200 g.
        self::assertSame(1200, Dimensions::fromCentimeters(30, 20, 10)->volumetricWeightGrams());
    }

    public function testAddressRejectsNonIsoCountry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Address('Nigeria', 'Lagos', '1 Street');
    }

    public function testLabelRequiresContentsOrUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Label(LabelFormat::PDF);
    }

    public function testRateQuoteCollectionSortsAndFindsCheapest(): void
    {
        $collection = new RateQuoteCollection(
            new RateQuote('p', 'a', 'A', Money::fromMinor(500, Currency::NGN)),
            new RateQuote('p', 'b', 'B', Money::fromMinor(200, Currency::NGN)),
            new RateQuote('p', 'c', 'C', Money::fromMinor(300, Currency::NGN)),
        );

        $sorted = $collection->sortedByPrice()->all();
        self::assertSame([200, 300, 500], array_map(static fn (RateQuote $q): int => $q->amount->minorAmount, $sorted));
        self::assertSame('b', $collection->cheapest()?->serviceCode);
    }

    public function testTrackingEventCollectionLatestIsMostRecent(): void
    {
        $older = new TrackingEvent('p', 'T1', TrackingStatus::CREATED, new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $newer = new TrackingEvent('p', 'T1', TrackingStatus::DELIVERED, new \DateTimeImmutable('2026-01-03T00:00:00+00:00'));

        // Insert out of order; latest() must still be the newest.
        $collection = new TrackingEventCollection($newer, $older);

        self::assertSame($newer, $collection->latest());
        self::assertSame(TrackingStatus::CREATED, $collection->sortedByTime()->all()[0]->status);
    }
}
