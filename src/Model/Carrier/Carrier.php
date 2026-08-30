<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Carrier;

/**
 * A courier a provider can ship with (GIG, DHL, The Courier Guy, …), in canonical
 * shape. `code` is the provider's stable carrier identifier — the value a store
 * owner's allow-list stores and the value a RateQuote's carrierCode matches, so
 * filtering never depends on display names.
 */
final readonly class Carrier
{
    /**
     * @param array<string, mixed> $providerData the raw provider payload for this
     *                                            carrier, opaque to the core —
     *                                            interpreted only via a provider's
     *                                            CarrierData struct, never read
     *                                            key-by-key in business logic
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $logoUrl = null,
        public array $providerData = [],
    ) {
    }
}
