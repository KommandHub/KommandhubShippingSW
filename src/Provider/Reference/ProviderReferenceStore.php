<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Reference;

/**
 * Remembers provider-side identifiers (Terminal address_id / parcel_id, etc.)
 * keyed by a content hash, so an adapter whose workflow requires pre-creating
 * resources doesn't recreate an identical one on every call. Checkout
 * recalculates deliveries constantly; without this, each recalculation would
 * POST a fresh address/parcel to the provider.
 *
 * Generic on purpose: any provider with a "create resource, get an id, reuse
 * the id" lifecycle can share it — the namespace keeps their key spaces apart.
 */
interface ProviderReferenceStore
{
    public function get(string $namespace, string $hash): ?string;

    public function save(string $namespace, string $hash, string $id, int $ttlSeconds): void;
}
