<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider\TerminalAfrica;

use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\ShippingProviderInterface;
use Kommandhub\ShippingSW\Provider\Reference\InMemoryProviderReferenceStore;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TerminalAfricaAdapter;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TShipMapper;
use Kommandhub\ShippingSW\Tests\Conformance\ProviderConformanceTestCase;
use Kommandhub\ShippingSW\Tests\Support\StubTerminalTransport;

/**
 * The real Terminal adapter must pass the SAME conformance suite the mock does,
 * driven by recorded fixtures. This is the regression proof that a live adapter
 * honours the contract, not just a hand-written mock.
 */
final class TerminalAfricaAdapterConformanceTest extends ProviderConformanceTestCase
{
    protected function provider(): ShippingProviderInterface
    {
        return new TerminalAfricaAdapter(new StubTerminalTransport(), new TShipMapper(), new InMemoryProviderReferenceStore());
    }

    protected function context(): ProviderContext
    {
        return new ProviderContext(apiKey: 'sk_test_terminal', sandbox: true, webhookSecret: 'whsec_terminal');
    }
}
