<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider;

use Kommandhub\ShippingSW\Setting\Service\Config;

/**
 * Turns per-sales-channel SystemConfig into an immutable ProviderContext and
 * resolves which provider a channel is configured to use. This is the one place
 * config keys are read, so the rest of the plugin depends on the typed context,
 * not on config-key strings.
 */
final class ProviderContextFactory
{
    public function __construct(private readonly Config $config)
    {
    }

    /** The provider key the sales channel is configured to use, if any. */
    public function activeProviderKey(?string $salesChannelId): ?string
    {
        $key = $this->config->getString('activeProvider', $salesChannelId);

        return '' === $key ? null : $key;
    }

    public function forSalesChannel(?string $salesChannelId): ProviderContext
    {
        $sandbox = $this->config->getBool('enableSandbox', $salesChannelId);

        $apiKey = $sandbox
            ? $this->config->getString('apiKeySandbox', $salesChannelId)
            : $this->config->getString('apiKey', $salesChannelId);

        $webhookSecret = $this->config->getString('webhookSecret', $salesChannelId);

        return new ProviderContext(
            apiKey: $apiKey,
            sandbox: $sandbox,
            webhookSecret: '' === $webhookSecret ? null : $webhookSecret,
            salesChannelId: $salesChannelId,
        );
    }
}
