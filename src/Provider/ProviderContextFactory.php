<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider;

use Kommandhub\ShippingSW\Provider\TerminalAfrica\TerminalAfricaAdapter;
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
            extra: $this->providerExtra($salesChannelId),
        );
    }

    /**
     * Provider-specific settings carried on ProviderContext::$extra. Kept here so
     * config keys live in one place; adapters read their own keys by constant.
     *
     * @return array<string, scalar|null>
     */
    private function providerExtra(?string $salesChannelId): array
    {
        $extra = [];

        $pickupAddressId = $this->config->getString(TerminalAfricaAdapter::EXTRA_PICKUP_ADDRESS_ID, $salesChannelId);
        if ('' !== $pickupAddressId) {
            $extra[TerminalAfricaAdapter::EXTRA_PICKUP_ADDRESS_ID] = $pickupAddressId;
        }

        $packagingId = $this->config->getString(TerminalAfricaAdapter::EXTRA_PACKAGING_ID, $salesChannelId);
        if ('' !== $packagingId) {
            $extra[TerminalAfricaAdapter::EXTRA_PACKAGING_ID] = $packagingId;
        }

        return $extra;
    }
}
