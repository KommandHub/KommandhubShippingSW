<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Exception;

final class DuplicateProviderException extends ProviderException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf(
            'Two shipping providers are registered with the same key "%s"; keys must be unique.',
            $key,
        ));
    }
}
