<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;

/**
 * Caches rate lookups so checkout doesn't hit a courier API on every cart
 * recalculation. Behind an interface so the storage (Shopware cache pool, or a
 * null cache in tests) is a wiring choice, not a code change.
 */
interface RateCache
{
    public function get(string $key): ?RateQuoteCollection;

    public function set(string $key, RateQuoteCollection $quotes, int $ttlSeconds): void;
}
