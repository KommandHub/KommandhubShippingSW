<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Client\Http;

/**
 * Process-local circuit state. Sufficient for a single web/worker process — a
 * tripped circuit protects that process for the cooldown window.
 *
 * ponytail: per-process only; swap in a cache-backed CircuitBreakerStorage if
 * circuits must be shared across workers.
 */
final class InMemoryCircuitBreakerStorage implements CircuitBreakerStorage
{
    /** @var array<string, array{failures: int, openedAt: int|null}> */
    private array $state = [];

    public function read(string $circuit): ?array
    {
        return $this->state[$circuit] ?? null;
    }

    public function write(string $circuit, array $state): void
    {
        $this->state[$circuit] = $state;
    }
}
