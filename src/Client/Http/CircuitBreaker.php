<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Client\Http;

/**
 * A minimal per-circuit breaker (closed → open → half-open).
 *
 *  - closed:    requests flow; consecutive failures are counted.
 *  - open:      once failures reach the threshold the circuit opens and blocks
 *               requests for cooldownSeconds.
 *  - half-open: after cooldown, one trial request is allowed; success closes the
 *               circuit, another failure re-opens it for a fresh cooldown.
 *
 * Time is injected as a closure so the behaviour is deterministically testable
 * without any clock dependency.
 */
final class CircuitBreaker
{
    /** @var \Closure(): int */
    private \Closure $clock;

    /**
     * @param (\Closure(): int)|null $clock returns a unix timestamp; defaults to time()
     */
    public function __construct(
        private readonly CircuitBreakerStorage $storage,
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 30,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function isOpen(string $circuit): bool
    {
        $state = $this->storage->read($circuit);
        if (null === $state || null === $state['openedAt']) {
            return false;
        }

        // Cooldown elapsed → allow a half-open trial (treated as not-open here).
        return ($this->clock)() < $state['openedAt'] + $this->cooldownSeconds;
    }

    /**
     * @throws CircuitBreakerOpenException when the circuit is open
     */
    public function guard(string $circuit): void
    {
        if ($this->isOpen($circuit)) {
            throw CircuitBreakerOpenException::forCircuit($circuit);
        }
    }

    public function recordSuccess(string $circuit): void
    {
        $this->storage->write($circuit, ['failures' => 0, 'openedAt' => null]);
    }

    public function recordFailure(string $circuit): void
    {
        $state = $this->storage->read($circuit) ?? ['failures' => 0, 'openedAt' => null];
        $failures = $state['failures'] + 1;

        $this->storage->write($circuit, [
            'failures' => $failures,
            'openedAt' => $failures >= $this->failureThreshold ? ($this->clock)() : $state['openedAt'],
        ]);
    }
}
