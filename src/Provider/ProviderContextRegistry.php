<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider;

use Kommandhub\ShippingSW\Setting\Service\Config;

/**
 * Core context resolver. Reads the active-provider selection from core config,
 * and delegates context construction to the provider's own configurator
 * (collected by tag). This is how a provider plugin owns its credentials without
 * core knowing its config keys.
 */
final class ProviderContextRegistry implements ProviderContextResolver
{
    /** @var array<string, ProviderConfigurator> */
    private array $configurators = [];

    /**
     * @param iterable<ProviderConfigurator> $configurators
     */
    public function __construct(
        private readonly Config $config,
        iterable $configurators,
    ) {
        foreach ($configurators as $configurator) {
            $this->configurators[$configurator->providerKey()] = $configurator;
        }
    }

    public function activeProviderKey(?string $salesChannelId): ?string
    {
        $key = $this->config->getString('activeProvider', $salesChannelId);

        return '' === $key ? null : $key;
    }

    public function forProvider(string $providerKey, ?string $salesChannelId): ProviderContext
    {
        $configurator = $this->configurators[$providerKey] ?? null;

        return null !== $configurator
            ? $configurator->forSalesChannel($salesChannelId)
            : new ProviderContext(apiKey: '', salesChannelId: $salesChannelId);
    }
}
