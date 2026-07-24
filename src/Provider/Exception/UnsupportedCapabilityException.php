<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Exception;

use Kommandhub\ShippingSW\Provider\Capability;

/**
 * Thrown when a capability method is invoked on a provider that does not
 * support it. Adapters should guard their unsupported methods with this.
 */
final class UnsupportedCapabilityException extends ProviderException
{
    public static function for(string $providerKey, Capability $capability): self
    {
        return new self(sprintf(
            'Provider "%s" does not support capability %s.',
            $providerKey,
            $capability->name,
        ));
    }
}
