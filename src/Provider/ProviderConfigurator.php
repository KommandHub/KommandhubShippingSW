<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider;

/**
 * Builds the ProviderContext (credentials + provider-specific settings) for one
 * provider from that provider's own configuration. Each provider — whether a
 * core module (Bob Go) or a separate plugin (Terminal) — ships its own
 * configurator and owns its config keys. Core never reads a provider's keys;
 * it resolves the context through the configurator collected by tag.
 */
interface ProviderConfigurator
{
    public function providerKey(): string;

    public function forSalesChannel(?string $salesChannelId): ProviderContext;
}
