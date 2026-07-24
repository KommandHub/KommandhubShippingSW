<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider;

/**
 * Per-call, per-sales-channel provider configuration: credentials and the
 * sandbox/live toggle. Passed into every interface method so adapters stay
 * stateless — one adapter instance serves every sales channel, reading the
 * right credentials from the context rather than from constructor state. That
 * is what lets a NG channel and a SA channel run different providers, and two
 * channels run the same provider with different keys, with no code change.
 */
final readonly class ProviderContext
{
    /**
     * @param array<string, scalar|null> $extra provider-specific settings that
     *                                           don't warrant a first-class field
     */
    public function __construct(
        public string $apiKey,
        public bool $sandbox = false,
        public ?string $webhookSecret = null,
        public ?string $salesChannelId = null,
        public array $extra = [],
    ) {
    }
}
