<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Client\Http;

use Kommandhub\ShippingSW\Client\Http\CircuitBreaker;
use Kommandhub\ShippingSW\Client\Http\CircuitBreakerOpenException;
use Kommandhub\ShippingSW\Client\Http\InMemoryCircuitBreakerStorage;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    private int $now = 1_000_000;

    private function breaker(): CircuitBreaker
    {
        return new CircuitBreaker(
            new InMemoryCircuitBreakerStorage(),
            failureThreshold: 3,
            cooldownSeconds: 30,
            clock: fn (): int => $this->now,
        );
    }

    public function testClosedWhileBelowThreshold(): void
    {
        $breaker = $this->breaker();
        $breaker->recordFailure('terminal');
        $breaker->recordFailure('terminal');

        self::assertFalse($breaker->isOpen('terminal'));
    }

    public function testOpensAtThresholdAndBlocks(): void
    {
        $breaker = $this->breaker();
        $breaker->recordFailure('terminal');
        $breaker->recordFailure('terminal');
        $breaker->recordFailure('terminal');

        self::assertTrue($breaker->isOpen('terminal'));

        $this->expectException(CircuitBreakerOpenException::class);
        $breaker->guard('terminal');
    }

    public function testHalfOpensAfterCooldown(): void
    {
        $breaker = $this->breaker();
        for ($i = 0; $i < 3; ++$i) {
            $breaker->recordFailure('terminal');
        }
        self::assertTrue($breaker->isOpen('terminal'));

        $this->now += 31; // past cooldown
        self::assertFalse($breaker->isOpen('terminal'), 'Circuit must half-open after cooldown.');
    }

    public function testSuccessResetsCircuit(): void
    {
        $breaker = $this->breaker();
        for ($i = 0; $i < 3; ++$i) {
            $breaker->recordFailure('terminal');
        }
        $breaker->recordSuccess('terminal');

        self::assertFalse($breaker->isOpen('terminal'));
    }

    public function testCircuitsAreIsolatedByName(): void
    {
        $breaker = $this->breaker();
        for ($i = 0; $i < 3; ++$i) {
            $breaker->recordFailure('terminal');
        }

        self::assertTrue($breaker->isOpen('terminal'));
        self::assertFalse($breaker->isOpen('bobgo'));
    }
}
