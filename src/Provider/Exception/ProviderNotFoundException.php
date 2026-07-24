<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Exception;

final class ProviderNotFoundException extends ProviderException
{
    /**
     * @param list<string> $available
     */
    public static function forKey(string $key, array $available): self
    {
        return new self(sprintf(
            'No shipping provider registered for key "%s". Available: %s.',
            $key,
            [] === $available ? '(none)' : implode(', ', $available),
        ));
    }
}
