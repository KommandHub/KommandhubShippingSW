<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider;

use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Tests\Support\MockProvider;
use PHPUnit\Framework\TestCase;

/**
 * Provider-specific behaviour that the generic conformance suite can't assert
 * because it depends on knowing this provider's signing scheme and pricing.
 */
final class MockProviderTest extends TestCase
{
    public function testValidWebhookSignatureVerifies(): void
    {
        $provider = new MockProvider();
        $context = new ProviderContext(apiKey: 'k', webhookSecret: 'whsec_test');
        $body = '{"event":"tracking.update","status":"delivered"}';

        $signature = MockProvider::sign($body, 'whsec_test');

        self::assertTrue($provider->verifyWebhook($body, $signature, $context));
    }

    public function testCheapestQuoteIsStandard(): void
    {
        $provider = new MockProvider();

        $quotes = $provider->getRates($this->rateRequest(), new ProviderContext(apiKey: 'k'));
        $cheapest = $quotes->cheapest();

        self::assertNotNull($cheapest);
        self::assertSame('standard', $cheapest->serviceCode);
        self::assertSame(Currency::NGN, $cheapest->amount->currency);
    }

    private function rateRequest(): RateRequest
    {
        return new RateRequest(
            new Address('NG', 'Lagos', '1 Sample Street'),
            new Address('NG', 'Abuja', '2 Buyer Road'),
            Weight::fromKilograms(2.0),
            Dimensions::fromCentimeters(30, 20, 10),
            Currency::NGN,
        );
    }
}
