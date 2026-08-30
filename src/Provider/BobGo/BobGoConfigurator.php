<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\BobGo;

use Kommandhub\ShippingSW\Provider\ProviderConfigurator;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Setting\Service\Config;

/**
 * Builds the Bob Go context from its own config keys. Bob Go is a core module,
 * so its config lives in core config.xml (bobgo*), but it still owns those keys
 * here rather than core reading them generically.
 */
final class BobGoConfigurator implements ProviderConfigurator
{
    public function __construct(private readonly Config $config)
    {
    }

    public function providerKey(): string
    {
        return BobGoMapper::PROVIDER_KEY;
    }

    public function forSalesChannel(?string $salesChannelId): ProviderContext
    {
        $sandbox = $this->config->getBool('bobgoEnableSandbox', $salesChannelId);

        $apiKey = $sandbox
            ? $this->config->getString('bobgoApiKeySandbox', $salesChannelId)
            : $this->config->getString('bobgoApiKey', $salesChannelId);

        $webhookSecret = $this->config->getString('bobgoWebhookSecret', $salesChannelId);

        return new ProviderContext(
            apiKey: $apiKey,
            sandbox: $sandbox,
            webhookSecret: '' === $webhookSecret ? null : $webhookSecret,
            salesChannelId: $salesChannelId,
        );
    }
}
