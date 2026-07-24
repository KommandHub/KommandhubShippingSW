<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\ValueObject;

/**
 * ISO-4217 currencies the plugin transacts in.
 *
 * decimals() is the number of minor units per major unit. Every currency here
 * is two-decimal, but the method exists so a zero-decimal (XOF, RWF) or
 * three-decimal (KWD) currency can be added later without any call site
 * hard-coding "* 100". Money multiplies by subunitFactor(), never by a literal.
 */
enum Currency: string
{
    case NGN = 'NGN';
    case ZAR = 'ZAR';
    case KES = 'KES';
    case GHS = 'GHS';

    public function decimals(): int
    {
        return match ($this) {
            self::NGN, self::ZAR, self::KES, self::GHS => 2,
        };
    }

    public function subunitFactor(): int
    {
        return 10 ** $this->decimals();
    }
}
