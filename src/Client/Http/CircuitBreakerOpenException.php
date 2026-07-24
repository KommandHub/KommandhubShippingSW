<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Client\Http;

use Kommandhub\ShippingSW\Exception\ShippingException;

/**
 * Raised when a request is short-circuited because the provider's circuit is
 * open. Callers (e.g. the rate aggregator) catch this to fall back to a
 * flat rate instead of hanging on a provider that is already known to be down.
 */
final class CircuitBreakerOpenException extends ShippingException
{
    public static function forCircuit(string $circuit): self
    {
        return new self(sprintf('Circuit "%s" is open; refusing request.', $circuit));
    }
}
