<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Catalog;

use Kommandhub\ShippingSW\Model\Carrier\CarrierCollection;
use Shopware\Core\Framework\Context;

/**
 * Persistent, provider-tagged carrier catalogue. The read side is what the admin
 * UI hits (fast, no provider API call); the write side is filled by the
 * synchroniser. Behind an interface so the synchroniser is unit-testable with an
 * in-memory catalogue.
 */
interface CarrierCatalog
{
    public function forProvider(string $providerKey, Context $context): CarrierCollection;

    /**
     * Replace the stored carriers for one provider with the given set (upsert
     * present, drop absent). Keyed by provider, so other providers are untouched.
     */
    public function replaceForProvider(string $providerKey, CarrierCollection $carriers, Context $context): void;
}
