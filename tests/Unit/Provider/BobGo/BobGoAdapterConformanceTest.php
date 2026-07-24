<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider\BobGo;

use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use Kommandhub\ShippingSW\Provider\BobGo\BobGoAdapter;
use Kommandhub\ShippingSW\Provider\BobGo\BobGoMapper;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;
use Kommandhub\ShippingSW\Tests\Conformance\ProviderConformanceTestCase;
use Kommandhub\ShippingSW\Tests\Support\StubBobGoTransport;

/**
 * Bob Go passes the SAME conformance suite as Terminal and the mock — including
 * the unsupported-capability half: it advertises no SCHEDULE_PICKUP, so the
 * suite verifies schedulePickup() throws. Region-specific inputs (ZAR + SA
 * address) are supplied by overriding the request builders; the contract is
 * unchanged.
 */
final class BobGoAdapterConformanceTest extends ProviderConformanceTestCase
{
    protected function provider(): ShippingProviderInterface
    {
        return new BobGoAdapter(new StubBobGoTransport(), new BobGoMapper());
    }

    protected function context(): ProviderContext
    {
        return new ProviderContext(apiKey: 'bobgo_test', sandbox: true, webhookSecret: 'whsec_bobgo');
    }

    protected function originAddress(): Address
    {
        return new Address('ZA', 'Cape Town', '1 Long Street', state: 'Western Cape', postalCode: '8001', name: 'Merchant', phone: '+27210000000');
    }

    protected function destinationAddress(): Address
    {
        return new Address('ZA', 'Durban', '2 Beach Road', state: 'KwaZulu-Natal', postalCode: '4001', name: 'Buyer', phone: '+27310000000');
    }

    protected function rateRequest(): RateRequest
    {
        return new RateRequest(
            $this->originAddress(),
            $this->destinationAddress(),
            Weight::fromKilograms(2.0),
            Dimensions::fromCentimeters(30, 20, 10),
            Currency::ZAR,
        );
    }

    protected function shipmentRequest(): ShipmentRequest
    {
        return new ShipmentRequest(
            providerKey: BobGoMapper::PROVIDER_KEY,
            serviceCode: 'ECO',
            origin: $this->originAddress(),
            destination: $this->destinationAddress(),
            weight: Weight::fromKilograms(2.0),
            dimensions: Dimensions::fromCentimeters(30, 20, 10),
            reference: 'ORDER-ZA-1',
            idempotencyKey: 'idem-ORDER-ZA-1',
        );
    }
}
