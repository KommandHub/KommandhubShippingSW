<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\ValueObject;

/**
 * A postal address in canonical shape. Every provider has its own field names
 * and required-field rules; the adapter maps to/from this. countryCode is
 * ISO-3166-1 alpha-2 so origin/destination country checks are provider-agnostic.
 */
final readonly class Address
{
    public function __construct(
        public string $countryCode,
        public string $city,
        public string $line1,
        public ?string $line2 = null,
        public ?string $state = null,
        public ?string $postalCode = null,
        public ?string $name = null,
        public ?string $company = null,
        public ?string $phone = null,
        public ?string $email = null,
    ) {
        if (2 !== \strlen($countryCode) || strtoupper($countryCode) !== $countryCode) {
            throw new \InvalidArgumentException(
                'countryCode must be an ISO-3166-1 alpha-2 code, got: ' . $countryCode,
            );
        }
    }
}
