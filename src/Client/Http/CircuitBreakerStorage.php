<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Client\Http;

/**
 * State store for the circuit breaker, keyed by circuit name (typically the
 * provider key or host). Kept behind an interface so the process-local default
 * can be swapped for a shared cache-backed store when multiple workers need to
 * trip the same circuit.
 */
interface CircuitBreakerStorage
{
    /**
     * @return array{failures: int, openedAt: int|null}|null null if unknown
     */
    public function read(string $circuit): ?array;

    /**
     * @param array{failures: int, openedAt: int|null} $state
     */
    public function write(string $circuit, array $state): void;
}
