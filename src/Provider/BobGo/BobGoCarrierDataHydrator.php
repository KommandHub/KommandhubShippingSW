<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Provider\BobGo;

use Kommandhub\ShippingSW\Provider\Carrier\CarrierData;
use Kommandhub\ShippingSW\Provider\Carrier\CarrierDataHydrator;

final class BobGoCarrierDataHydrator implements CarrierDataHydrator
{
    public function providerKey(): string
    {
        return BobGoMapper::PROVIDER_KEY;
    }

    public function hydrate(array $raw): CarrierData
    {
        return new BobGoCarrierData(
            name: (string) ($raw['name'] ?? ''),
            raw: $raw,
        );
    }
}
