<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Checkout\Rate;

use Kommandhub\ShippingSW\Checkout\Rate\CarrierAllowList;
use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class CarrierAllowListTest extends TestCase
{
    private function quotes(): RateQuoteCollection
    {
        return new RateQuoteCollection(
            new RateQuote('terminal_africa', 'r1', 'GIG', Money::fromMinor(1000, Currency::NGN), carrierCode: 'gig'),
            new RateQuote('terminal_africa', 'r2', 'DHL', Money::fromMinor(2000, Currency::NGN), carrierCode: 'dhl'),
            new RateQuote('terminal_africa', 'r3', 'UPS', Money::fromMinor(3000, Currency::NGN), carrierCode: 'ups'),
        );
    }

    public function testEmptyAllowListIsUnrestricted(): void
    {
        $list = new CarrierAllowList([]);

        self::assertTrue($list->isUnrestricted());
        self::assertCount(3, $list->filter($this->quotes()));
    }

    public function testFiltersToApprovedCarriersOnly(): void
    {
        $filtered = (new CarrierAllowList(['gig', 'ups']))->filter($this->quotes());

        self::assertSame(['gig', 'ups'], array_map(static fn (RateQuote $q): ?string => $q->carrierCode, $filtered->all()));
    }

    public function testQuotesWithoutACodeAreExcludedWhenRestricted(): void
    {
        $quotes = new RateQuoteCollection(
            new RateQuote('p', 'r', 'Unknown', Money::fromMinor(1000, Currency::NGN)), // no carrierCode
        );

        self::assertTrue((new CarrierAllowList(['gig']))->filter($quotes)->isEmpty());
    }

    public function testBlankCodesAreIgnoredInTheList(): void
    {
        // A list of only blank strings behaves as unrestricted, not "block all".
        self::assertTrue((new CarrierAllowList(['', '']))->isUnrestricted());
    }
}
