<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Carrier;

/**
 * Fallback for a provider with no registered hydrator (or no rich payload). It
 * imposes no restrictions, so business logic degrades gracefully rather than
 * crashing on an unknown provider.
 */
final readonly class NullCarrierData implements CarrierData
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private string $providerKey,
        private array $raw = [],
    ) {
    }

    public function providerKey(): string
    {
        return $this->providerKey;
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function deliversTo(string $countryIso): bool
    {
        return true;
    }
}
