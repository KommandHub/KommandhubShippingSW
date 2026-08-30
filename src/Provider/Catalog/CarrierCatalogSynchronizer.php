<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Catalog;

use Kommandhub\ShippingSW\Model\Carrier\CarrierCollection;
use Kommandhub\ShippingSW\Provider\Capability;
use Kommandhub\ShippingSW\Provider\Exception\ProviderException;
use Kommandhub\ShippingSW\Provider\ProviderContextResolver;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

/**
 * Prefetches each provider's carrier catalogue into the store, so no customer or
 * admin ever waits on a live listCarriers() call. Run on a schedule and on
 * demand (admin "refresh").
 *
 * Uses the global provider credentials (null sales channel). If a provider's
 * carriers legitimately differ per sales channel, that would become a per-channel
 * sync — not needed for Terminal/Bob Go, whose catalogues are account-global.
 */
final class CarrierCatalogSynchronizer
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderContextResolver $contextFactory,
        private readonly CarrierCatalog $catalog,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Sync one provider; returns what was stored. No-op (empty) if the provider
     * can't list carriers.
     */
    public function sync(string $providerKey, Context $context): CarrierCollection
    {
        if (!$this->registry->has($providerKey)) {
            return new CarrierCollection();
        }

        $provider = $this->registry->get($providerKey);
        if (!$provider->supports(Capability::LIST_CARRIERS)) {
            return new CarrierCollection();
        }

        $carriers = $provider->listCarriers($this->contextFactory->forProvider($providerKey, null));
        $this->catalog->replaceForProvider($providerKey, $carriers, $context);

        $this->logger->info('Carrier catalogue synced', ['provider' => $providerKey, 'count' => $carriers->count()]);

        return $carriers;
    }

    /**
     * Sync every provider that can list carriers. One provider's failure does not
     * abort the rest.
     */
    public function syncAll(Context $context): void
    {
        foreach ($this->registry->supporting(Capability::LIST_CARRIERS) as $provider) {
            try {
                $this->sync($provider->key(), $context);
            } catch (ProviderException $e) {
                $this->logger->warning('Carrier sync failed for provider', [
                    'provider' => $provider->key(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
