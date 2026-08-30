<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\BobGo;

use Kommandhub\ShippingSW\Provider\Carrier\CarrierData;

/**
 * Typed view over a Bob Go provider object. Bob Go's catalogue carries no
 * per-carrier country coverage, so deliversTo() is unrestricted; the struct
 * still exists so business logic has one consistent, typed shape across
 * providers and can grow if Bob Go's payload does.
 */
final readonly class BobGoCarrierData implements CarrierData
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private string $name,
        private array $raw,
    ) {
    }

    public function providerKey(): string
    {
        return BobGoMapper::PROVIDER_KEY;
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function deliversTo(string $countryIso): bool
    {
        return true;
    }

    public function name(): string
    {
        return $this->name;
    }
}
