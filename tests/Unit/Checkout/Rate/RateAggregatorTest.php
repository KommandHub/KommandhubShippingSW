<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Checkout\Rate;

use Kommandhub\ShippingSW\Checkout\Rate\RateAggregator;
use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use Kommandhub\ShippingSW\Provider\ProviderContextFactory;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;
use Kommandhub\ShippingSW\Tests\Support\ArrayRateCache;
use Kommandhub\ShippingSW\Tests\Support\FailingProvider;
use Kommandhub\ShippingSW\Tests\Support\FakeConfig;
use Kommandhub\ShippingSW\Tests\Support\MockProvider;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

/**
 * The aggregator is where "checkout always gets a shippable option" is enforced.
 * These tests pin the three outcomes: live quotes (priced + cached), and both
 * fallback paths (no provider, provider failure).
 */
final class RateAggregatorTest extends TestCase
{
    private function aggregator(ShippingProviderInterface $provider, array $config, ArrayRateCache $cache): RateAggregator
    {
        $fakeConfig = new FakeConfig($config);

        return new RateAggregator(
            new ProviderRegistry([$provider]),
            new ProviderContextFactory($fakeConfig),
            $cache,
            $fakeConfig,
            new NullLogger(),
        );
    }

    private function request(): RateRequest
    {
        return new RateRequest(
            new Address('NG', 'Lagos', '1 Sample Street'),
            new Address('NG', 'Abuja', '2 Buyer Road'),
            Weight::fromKilograms(2.0),
            Dimensions::fromCentimeters(30, 20, 10),
            Currency::NGN,
        );
    }

    public function testLiveQuotesArePricedSortedAndCached(): void
    {
        $cache = new ArrayRateCache();
        $aggregator = $this->aggregator(
            new MockProvider('terminal_africa'),
            ['activeProvider' => 'terminal_africa', 'rateMarkupPercent' => 10.0],
            $cache,
        );

        $quotes = $aggregator->quote($this->request(), 'sc-1');

        self::assertGreaterThan(0, $quotes->count());
        // Cheapest first.
        self::assertSame($quotes->all()[0], $quotes->cheapest());
        // MockProvider standard = 152000 minor; +10% markup = 167200.
        self::assertSame(167200, $quotes->cheapest()?->amount->minorAmount);
        self::assertSame(1, $cache->writes, 'Priced quotes must be cached.');
    }

    public function testCacheHitShortCircuitsTheProvider(): void
    {
        $cache = new ArrayRateCache();
        $config = ['activeProvider' => 'terminal_africa'];

        // Warm the cache with a working provider.
        $this->aggregator(new MockProvider('terminal_africa'), $config, $cache)
            ->quote($this->request(), 'sc-1');

        // A second aggregator whose provider would throw if consulted; the cache
        // hit (same key) must serve the stored quotes instead of falling back.
        $quotes = $this->aggregator(new FailingProvider('terminal_africa'), $config, $cache)
            ->quote($this->request(), 'sc-1');

        self::assertSame('terminal_africa', $quotes->all()[0]->providerKey);
        self::assertNotSame('fallback', $quotes->all()[0]->providerKey);
    }

    public function testAllowListFiltersCarriersBeforeCheckout(): void
    {
        $cache = new ArrayRateCache();
        // MockProvider returns carriers mock_express + mock_standard; approve only standard.
        $aggregator = $this->aggregator(
            new MockProvider('terminal_africa'),
            ['activeProvider' => 'terminal_africa', 'allowedCarriers' => ['mock_standard']],
            $cache,
        );

        $quotes = $aggregator->quote($this->request(), 'sc-1');

        self::assertCount(1, $quotes);
        self::assertSame('mock_standard', $quotes->all()[0]->carrierCode);
    }

    public function testAllowListExcludingEverythingFallsBack(): void
    {
        $aggregator = $this->aggregator(
            new MockProvider('terminal_africa'),
            ['activeProvider' => 'terminal_africa', 'allowedCarriers' => ['carrier_that_does_not_quote'], 'flatRateFallback' => 999.0],
            new ArrayRateCache(),
        );

        $quotes = $aggregator->quote($this->request(), 'sc-1');

        self::assertSame('fallback', $quotes->all()[0]->providerKey);
    }

    public function testNoActiveProviderReturnsFlatRate(): void
    {
        $cache = new ArrayRateCache();
        $aggregator = $this->aggregator(
            new MockProvider('terminal_africa'),
            ['flatRateFallback' => 1500.0], // no activeProvider set
            $cache,
        );

        $quotes = $aggregator->quote($this->request(), 'sc-1');

        self::assertCount(1, $quotes);
        self::assertSame('fallback', $quotes->all()[0]->providerKey);
        self::assertSame(150000, $quotes->all()[0]->amount->minorAmount);
    }

    public function testProviderFailureFallsBackToFlatRate(): void
    {
        $cache = new ArrayRateCache();
        $aggregator = $this->aggregator(
            new FailingProvider('terminal_africa'),
            ['activeProvider' => 'terminal_africa', 'flatRateFallback' => 2000.0],
            $cache,
        );

        $quotes = $aggregator->quote($this->request(), 'sc-1');

        self::assertSame('fallback', $quotes->all()[0]->providerKey);
        self::assertSame(200000, $quotes->all()[0]->amount->minorAmount);
        self::assertSame(0, $cache->writes, 'Fallback rates are not cached.');
    }
}
