<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Checkout\Rate;

use Kommandhub\ShippingSW\Checkout\Rate\RateMarkup;
use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class RateMarkupTest extends TestCase
{
    private function quote(int $minor): RateQuote
    {
        return new RateQuote('terminal_africa', 'std', 'Standard', Money::fromMinor($minor, Currency::NGN));
    }

    public function testPercentAndHandlingApplied(): void
    {
        $marked = (new RateMarkup(percent: 10.0, handlingFeeMinor: 5000))->applyTo($this->quote(100000));

        // 100000 * 1.10 = 110000, + 5000 handling = 115000
        self::assertSame(115000, $marked->amount->minorAmount);
        self::assertSame(Currency::NGN, $marked->amount->currency);
    }

    public function testZeroMarkupIsIdentityOnAmount(): void
    {
        $marked = (new RateMarkup())->applyTo($this->quote(250000));

        self::assertSame(250000, $marked->amount->minorAmount);
    }

    public function testPreservesServiceMetadata(): void
    {
        $original = new RateQuote('terminal_africa', 'exp', 'Express', Money::fromMinor(700000, Currency::NGN), 1, 2, 'DHL');
        $marked = (new RateMarkup(percent: 5.0))->applyTo($original);

        self::assertSame('exp', $marked->serviceCode);
        self::assertSame('DHL', $marked->carrierName);
        self::assertSame(1, $marked->estimatedDaysMin);
    }
}
