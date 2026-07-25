<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Reference;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Reference store backed by a PSR-6 pool (Shopware's cache.object), so a
 * created address/parcel id is reused across requests and workers until it
 * expires — not just within one process.
 */
final class PsrProviderReferenceStore implements ProviderReferenceStore
{
    public function __construct(private readonly CacheItemPoolInterface $pool)
    {
    }

    public function get(string $namespace, string $hash): ?string
    {
        $item = $this->pool->getItem($this->key($namespace, $hash));
        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        return \is_string($value) ? $value : null;
    }

    public function save(string $namespace, string $hash, string $id, int $ttlSeconds): void
    {
        $item = $this->pool->getItem($this->key($namespace, $hash));
        $item->set($id);
        $item->expiresAfter($ttlSeconds);
        $this->pool->save($item);
    }

    private function key(string $namespace, string $hash): string
    {
        return 'kh_ship_ref_' . $namespace . '_' . $hash;
    }
}
