<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Rate cache backed by a PSR-6 pool (Shopware's cache.object). Collections are
 * immutable value objects, so serialise/unserialise round-trips them safely.
 */
final class PsrRateCache implements RateCache
{
    public function __construct(private readonly CacheItemPoolInterface $pool)
    {
    }

    public function get(string $key): ?RateQuoteCollection
    {
        $item = $this->pool->getItem($key);
        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        return $value instanceof RateQuoteCollection ? $value : null;
    }

    public function set(string $key, RateQuoteCollection $quotes, int $ttlSeconds): void
    {
        $item = $this->pool->getItem($key);
        $item->set($quotes);
        $item->expiresAfter($ttlSeconds);
        $this->pool->save($item);
    }
}
