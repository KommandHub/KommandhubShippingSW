<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Reference;

/**
 * Process-local reference store. Used in tests and as a safe default; the
 * PSR-6-backed store shares ids across requests/workers.
 */
final class InMemoryProviderReferenceStore implements ProviderReferenceStore
{
    /** @var array<string, string> */
    private array $ids = [];

    public function get(string $namespace, string $hash): ?string
    {
        return $this->ids[$namespace . ':' . $hash] ?? null;
    }

    public function save(string $namespace, string $hash, string $id, int $ttlSeconds): void
    {
        $this->ids[$namespace . ':' . $hash] = $id;
    }
}
