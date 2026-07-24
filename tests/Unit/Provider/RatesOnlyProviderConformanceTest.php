<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider;

use Kommandhub\ShippingSW\Provider\Capability;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;
use Kommandhub\ShippingSW\Tests\Conformance\ProviderConformanceTestCase;
use Kommandhub\ShippingSW\Tests\Support\MockProvider;

/**
 * A provider that advertises only GET_RATES must still pass conformance: the
 * suite asserts the supported operation works AND that every unsupported
 * operation throws UnsupportedCapabilityException. This proves the two-sided
 * capability contract, not just the happy path.
 */
final class RatesOnlyProviderConformanceTest extends ProviderConformanceTestCase
{
    protected function provider(): ShippingProviderInterface
    {
        return new MockProvider('mock_rates_only', [Capability::GET_RATES]);
    }

    protected function context(): ProviderContext
    {
        return new ProviderContext(apiKey: 'test-key', sandbox: true);
    }
}
