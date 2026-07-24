<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\ValueObject;

/**
 * A monetary amount held as an integer number of minor units (kobo, cents),
 * paired with its currency. Integer storage avoids binary-float rounding on
 * money; the currency decides how many minor units make a major unit.
 *
 * The core and every adapter speak Money — a provider's raw "1500.00" string
 * is normalized to Money at the adapter boundary and never leaks inward.
 */
final readonly class Money
{
    public function __construct(
        public int $minorAmount,
        public Currency $currency,
    ) {
    }

    public static function fromMinor(int $minorAmount, Currency $currency): self
    {
        return new self($minorAmount, $currency);
    }

    /**
     * Build from a major-unit amount (e.g. 1500.00 NGN). Accepts a numeric
     * string to avoid float drift on the way in; falsy precision beyond the
     * currency's decimals is rounded, not truncated.
     */
    public static function fromMajor(string|int|float $major, Currency $currency): self
    {
        $minor = (int) round(((float) $major) * $currency->subunitFactor());

        return new self($minor, $currency);
    }

    /** Major-unit representation as a fixed-decimal string, e.g. "1500.00". */
    public function major(): string
    {
        return number_format(
            $this->minorAmount / $this->currency->subunitFactor(),
            $this->currency->decimals(),
            '.',
            '',
        );
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorAmount + $other->minorAmount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorAmount - $other->minorAmount, $this->currency);
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minorAmount < $other->minorAmount;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && $this->minorAmount === $other->minorAmount;
    }

    public function isZero(): bool
    {
        return 0 === $this->minorAmount;
    }

    public function isPositive(): bool
    {
        return $this->minorAmount > 0;
    }

    /** Human/log-friendly, e.g. "NGN 1500.00". */
    public function format(): string
    {
        return $this->currency->value . ' ' . $this->major();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot combine %s with %s: currency mismatch.',
                $this->currency->value,
                $other->currency->value,
            ));
        }
    }
}
