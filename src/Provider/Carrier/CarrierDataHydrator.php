<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Carrier;

/**
 * Maps a provider's raw carrier payload into its typed CarrierData. One per
 * provider, collected by tag — adding a provider means adding a hydrator, no
 * core change. This is where each provider's JSON shape is known; nowhere else.
 */
interface CarrierDataHydrator
{
    public function providerKey(): string;

    /**
     * @param array<string, mixed> $raw
     */
    public function hydrate(array $raw): CarrierData;
}
