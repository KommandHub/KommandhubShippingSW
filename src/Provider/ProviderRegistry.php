<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider;

use Kommandhub\ShippingSW\Provider\Exception\DuplicateProviderException;
use Kommandhub\ShippingSW\Provider\Exception\ProviderNotFoundException;

/**
 * Resolves a ShippingProviderInterface by its config key. Adapters are
 * collected through the "kommandhub_shipping.provider" service tag (see
 * services.yml), so registering a new courier is a tag, not an edit here.
 */
final class ProviderRegistry
{
    /** @var array<string, ShippingProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<ShippingProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $key = $provider->key();
            if (isset($this->providers[$key])) {
                throw DuplicateProviderException::forKey($key);
            }
            $this->providers[$key] = $provider;
        }
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function get(string $key): ShippingProviderInterface
    {
        return $this->providers[$key]
            ?? throw ProviderNotFoundException::forKey($key, array_keys($this->providers));
    }

    /**
     * @return array<string, ShippingProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Every registered provider advertising the given capability.
     *
     * @return list<ShippingProviderInterface>
     */
    public function supporting(Capability $capability): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn (ShippingProviderInterface $p): bool => $p->supports($capability),
        ));
    }
}
