<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider;

use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;
use Kommandhub\ShippingSW\Tests\Conformance\ProviderConformanceTestCase;
use Kommandhub\ShippingSW\Tests\Support\MockProvider;

/**
 * The reference provider must pass the full conformance suite. This is the
 * Phase 0 "done" gate.
 */
final class MockProviderConformanceTest extends ProviderConformanceTestCase
{
    protected function provider(): ShippingProviderInterface
    {
        return new MockProvider();
    }

    protected function context(): ProviderContext
    {
        return new ProviderContext(apiKey: 'test-key', sandbox: true, webhookSecret: 'whsec_test');
    }
}
