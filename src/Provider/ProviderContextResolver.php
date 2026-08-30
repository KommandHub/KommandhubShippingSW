<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider;

/**
 * Resolves which provider a sales channel uses and builds that provider's
 * context. Context construction is delegated to per-provider configurators, so
 * core stays ignorant of any provider's config keys.
 */
interface ProviderContextResolver
{
    /** The provider key the sales channel is configured to use, if any. */
    public function activeProviderKey(?string $salesChannelId): ?string;

    /**
     * Build the context for a given provider. Returns an empty context if no
     * configurator is registered for the key (provider plugin not installed).
     */
    public function forProvider(string $providerKey, ?string $salesChannelId): ProviderContext;
}
