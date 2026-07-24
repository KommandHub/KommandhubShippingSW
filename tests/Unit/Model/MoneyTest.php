<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Model;

use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testFromMajorConvertsToMinorUnits(): void
    {
        $money = Money::fromMajor('1500.00', Currency::NGN);

        self::assertSame(150000, $money->minorAmount);
        self::assertSame('1500.00', $money->major());
        self::assertSame('NGN 1500.00', $money->format());
    }

    public function testFromMajorRoundsToCurrencyPrecision(): void
    {
        self::assertSame(1235, Money::fromMajor('12.345', Currency::ZAR)->minorAmount);
    }

    public function testAddRequiresMatchingCurrency(): void
    {
        $ngn = Money::fromMinor(100, Currency::NGN);
        $zar = Money::fromMinor(100, Currency::ZAR);

        $this->expectException(\InvalidArgumentException::class);
        $ngn->add($zar);
    }

    public function testAddSameCurrency(): void
    {
        $sum = Money::fromMinor(150, Currency::KES)->add(Money::fromMinor(350, Currency::KES));

        self::assertTrue($sum->equals(Money::fromMinor(500, Currency::KES)));
    }

    public function testEqualsIsCurrencyAware(): void
    {
        self::assertFalse(
            Money::fromMinor(100, Currency::NGN)->equals(Money::fromMinor(100, Currency::GHS)),
        );
    }
}
