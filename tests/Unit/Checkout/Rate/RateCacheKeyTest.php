<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Checkout\Rate;

use Kommandhub\ShippingSW\Checkout\Rate\RateCacheKey;
use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use PHPUnit\Framework\TestCase;

final class RateCacheKeyTest extends TestCase
{
    public function testEquivalentRequestsShareAKey(): void
    {
        self::assertSame(
            RateCacheKey::for($this->request(), 'terminal', false),
            RateCacheKey::for($this->request(), 'terminal', false),
        );
    }

    public function testWeightChangesTheKey(): void
    {
        $heavier = new RateRequest(
            $this->origin(),
            $this->destination(),
            Weight::fromKilograms(5.0),
            Dimensions::fromCentimeters(30, 20, 10),
            Currency::NGN,
        );

        self::assertNotSame(
            RateCacheKey::for($this->request(), 'terminal', false),
            RateCacheKey::for($heavier, 'terminal', false),
        );
    }

    public function testProviderAndModeChangeTheKey(): void
    {
        $base = RateCacheKey::for($this->request(), 'terminal', false);

        self::assertNotSame($base, RateCacheKey::for($this->request(), 'bobgo', false));
        self::assertNotSame($base, RateCacheKey::for($this->request(), 'terminal', true));
    }

    private function request(): RateRequest
    {
        return new RateRequest(
            $this->origin(),
            $this->destination(),
            Weight::fromKilograms(2.0),
            Dimensions::fromCentimeters(30, 20, 10),
            Currency::NGN,
        );
    }

    private function origin(): Address
    {
        return new Address('NG', 'Lagos', '1 Sample Street', postalCode: '100001');
    }

    private function destination(): Address
    {
        return new Address('NG', 'Abuja', '2 Buyer Road', postalCode: '900001');
    }
}
