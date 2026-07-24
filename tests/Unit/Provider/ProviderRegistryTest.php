<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Unit\Provider;

use Kommandhub\ShippingSW\Provider\Capability;
use Kommandhub\ShippingSW\Provider\Exception\DuplicateProviderException;
use Kommandhub\ShippingSW\Provider\Exception\ProviderNotFoundException;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Kommandhub\ShippingSW\Tests\Support\MockProvider;
use PHPUnit\Framework\TestCase;

final class ProviderRegistryTest extends TestCase
{
    public function testResolvesByKey(): void
    {
        $terminal = new MockProvider('terminal_africa');
        $bobgo = new MockProvider('bobgo');
        $registry = new ProviderRegistry([$terminal, $bobgo]);

        self::assertTrue($registry->has('terminal_africa'));
        self::assertSame($terminal, $registry->get('terminal_africa'));
        self::assertSame($bobgo, $registry->get('bobgo'));
        self::assertCount(2, $registry->all());
    }

    public function testUnknownKeyThrows(): void
    {
        $registry = new ProviderRegistry([new MockProvider('terminal_africa')]);

        $this->expectException(ProviderNotFoundException::class);
        $registry->get('does_not_exist');
    }

    public function testDuplicateKeyThrows(): void
    {
        $this->expectException(DuplicateProviderException::class);
        new ProviderRegistry([new MockProvider('dup'), new MockProvider('dup')]);
    }

    public function testSupportingFiltersByCapability(): void
    {
        $full = new MockProvider('full');
        $ratesOnly = new MockProvider('rates_only', [Capability::GET_RATES]);
        $registry = new ProviderRegistry([$full, $ratesOnly]);

        $canLabel = $registry->supporting(Capability::GENERATE_LABEL);
        self::assertSame([$full], $canLabel);

        $canRate = $registry->supporting(Capability::GET_RATES);
        self::assertCount(2, $canRate);
    }
}
