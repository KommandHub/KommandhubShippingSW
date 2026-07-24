<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Support;

use Kommandhub\ShippingSW\Checkout\Rate\RateCache;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;

/**
 * In-memory RateCache for tests. Records writes so a test can assert caching.
 */
final class ArrayRateCache implements RateCache
{
    /** @var array<string, RateQuoteCollection> */
    public array $store = [];

    public int $writes = 0;

    public function get(string $key): ?RateQuoteCollection
    {
        return $this->store[$key] ?? null;
    }

    public function set(string $key, RateQuoteCollection $quotes, int $ttlSeconds): void
    {
        $this->store[$key] = $quotes;
        ++$this->writes;
    }
}
