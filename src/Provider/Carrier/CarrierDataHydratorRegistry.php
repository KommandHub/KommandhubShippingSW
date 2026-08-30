<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\Carrier;

/**
 * Resolves the right CarrierData hydrator by provider key. Falls back to
 * NullCarrierData so an unmapped provider never breaks a caller.
 */
final class CarrierDataHydratorRegistry
{
    /** @var array<string, CarrierDataHydrator> */
    private array $hydrators = [];

    /**
     * @param iterable<CarrierDataHydrator> $hydrators
     */
    public function __construct(iterable $hydrators)
    {
        foreach ($hydrators as $hydrator) {
            $this->hydrators[$hydrator->providerKey()] = $hydrator;
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    public function hydrate(string $providerKey, array $raw): CarrierData
    {
        $hydrator = $this->hydrators[$providerKey] ?? null;

        return null !== $hydrator
            ? $hydrator->hydrate($raw)
            : new NullCarrierData($providerKey, $raw);
    }
}
