<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider\Carrier;

use Kommandhub\ShippingSW\Provider\BobGo\BobGoCarrierData;
use Kommandhub\ShippingSW\Provider\BobGo\BobGoCarrierDataHydrator;
use Kommandhub\ShippingSW\Provider\Carrier\CarrierData;
use Kommandhub\ShippingSW\Provider\Carrier\CarrierDataHydrator;
use Kommandhub\ShippingSW\Provider\Carrier\CarrierDataHydratorRegistry;
use Kommandhub\ShippingSW\Provider\Carrier\NullCarrierData;
use PHPUnit\Framework\TestCase;

final class CarrierDataHydratorRegistryTest extends TestCase
{
    private function registry(): CarrierDataHydratorRegistry
    {
        // Bob Go (real) + a stub for an arbitrary provider key.
        $stub = new class implements CarrierDataHydrator {
            public function providerKey(): string
            {
                return 'stub';
            }

            public function hydrate(array $raw): CarrierData
            {
                return new NullCarrierData('stub', $raw);
            }
        };

        return new CarrierDataHydratorRegistry([new BobGoCarrierDataHydrator(), $stub]);
    }

    public function testResolvesProviderSpecificType(): void
    {
        self::assertInstanceOf(BobGoCarrierData::class, $this->registry()->hydrate('bobgo', ['name' => 'n']));
    }

    public function testUnknownProviderFallsBackToNull(): void
    {
        $data = $this->registry()->hydrate('shipbubble', ['foo' => 'bar']);

        self::assertInstanceOf(NullCarrierData::class, $data);
        self::assertSame('shipbubble', $data->providerKey());
        self::assertTrue($data->deliversTo('NG')); // unrestricted fallback
        self::assertSame('bar', $data->raw()['foo']);
    }
}
