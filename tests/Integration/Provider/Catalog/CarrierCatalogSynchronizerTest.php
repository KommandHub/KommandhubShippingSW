<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Tests\Integration\Provider\Catalog;

use Kommandhub\ShippingSW\Model\Carrier\CarrierCollection;
use Kommandhub\ShippingSW\Provider\Capability;
use Kommandhub\ShippingSW\Provider\Catalog\CarrierCatalog;
use Kommandhub\ShippingSW\Provider\Catalog\CarrierCatalogSynchronizer;
use Kommandhub\ShippingSW\Provider\ProviderContext;
use Kommandhub\ShippingSW\Provider\ProviderContextResolver;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Kommandhub\ShippingSW\Tests\Support\MockProvider;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use PHPUnit\Framework\TestCase;

/**
 * The prefetch logic: every provider's carriers are pulled and stored TAGGED BY
 * PROVIDER, so the config UI reads the right set from the DB with no live call.
 *
 * Lives under tests/Integration because it references Shopware's Context; it is
 * exercised by `make test`, not the host-only unit run (which has no Shopware).
 * The catalogue and context resolver are in-memory fakes — no DB is touched.
 */
final class CarrierCatalogSynchronizerTest extends TestCase
{
    private function catalog(): CarrierCatalog
    {
        return new class implements CarrierCatalog {
            /** @var array<string, CarrierCollection> */
            public array $stored = [];

            public function forProvider(string $providerKey, Context $context): CarrierCollection
            {
                return $this->stored[$providerKey] ?? new CarrierCollection();
            }

            public function replaceForProvider(string $providerKey, CarrierCollection $carriers, Context $context): void
            {
                $this->stored[$providerKey] = $carriers;
            }
        };
    }

    private function resolver(): ProviderContextResolver
    {
        return new class implements ProviderContextResolver {
            public function activeProviderKey(?string $salesChannelId): ?string
            {
                return null;
            }

            public function forProvider(string $providerKey, ?string $salesChannelId): ProviderContext
            {
                return new ProviderContext(apiKey: 'test');
            }
        };
    }

    public function testSyncStoresCarriersTaggedByProvider(): void
    {
        $catalog = $this->catalog();
        $registry = new ProviderRegistry([new MockProvider('terminal_africa'), new MockProvider('bobgo')]);
        $sync = new CarrierCatalogSynchronizer($registry, $this->resolver(), $catalog, new NullLogger());
        $context = Context::createDefaultContext();

        $stored = $sync->sync('terminal_africa', $context);

        self::assertSame(['mock_express', 'mock_standard'], $stored->codes());
        self::assertSame(['mock_express', 'mock_standard'], $catalog->forProvider('terminal_africa', $context)->codes());
        // Only the requested provider is synced.
        self::assertTrue($catalog->forProvider('bobgo', $context)->isEmpty());
    }

    public function testSyncAllStoresEveryCapableProvider(): void
    {
        $catalog = $this->catalog();
        $registry = new ProviderRegistry([new MockProvider('terminal_africa'), new MockProvider('bobgo')]);
        $sync = new CarrierCatalogSynchronizer($registry, $this->resolver(), $catalog, new NullLogger());
        $context = Context::createDefaultContext();

        $sync->syncAll($context);

        self::assertCount(2, $catalog->forProvider('terminal_africa', $context));
        self::assertCount(2, $catalog->forProvider('bobgo', $context));
    }

    public function testProviderWithoutListCarriersIsSkipped(): void
    {
        $catalog = $this->catalog();
        $registry = new ProviderRegistry([new MockProvider('ratesonly', [Capability::GET_RATES])]);
        $sync = new CarrierCatalogSynchronizer($registry, $this->resolver(), $catalog, new NullLogger());
        $context = Context::createDefaultContext();

        $result = $sync->sync('ratesonly', $context);

        self::assertTrue($result->isEmpty());
        self::assertTrue($catalog->forProvider('ratesonly', $context)->isEmpty());
    }
}
