<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use Kommandhub\ShippingSW\Provider\Exception\ProviderException;
use Kommandhub\ShippingSW\Provider\ProviderContextFactory;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Kommandhub\ShippingSW\Setting\Service\Config;
use Psr\Log\LoggerInterface;

/**
 * The checkout-facing rate service: resolves the sales channel's active
 * provider, serves cached quotes when possible, applies merchant markup, sorts
 * cheapest-first, and — crucially — degrades to a configured flat rate whenever
 * the provider is slow, erroring, or its circuit is open. Checkout must always
 * get a shippable option; it must never hang on or fail because of a courier.
 *
 * Provider specifics never appear here: it talks to ProviderRegistry and the
 * canonical model only.
 */
final class RateAggregator
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderContextFactory $contextFactory,
        private readonly RateCache $cache,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function quote(RateRequest $request, ?string $salesChannelId): RateQuoteCollection
    {
        $providerKey = $this->contextFactory->activeProviderKey($salesChannelId);
        if (null === $providerKey || !$this->registry->has($providerKey)) {
            $this->logger->warning('No active shipping provider configured; using flat rate', ['salesChannelId' => $salesChannelId]);

            return $this->fallback($request);
        }

        $context = $this->contextFactory->forSalesChannel($salesChannelId);
        $cacheKey = RateCacheKey::for($request, $providerKey, $context->sandbox);

        $cached = $this->cache->get($cacheKey);
        if (null !== $cached) {
            return $cached;
        }

        try {
            $raw = $this->registry->get($providerKey)->getRates($request, $context);
        } catch (ProviderException $e) {
            // Covers transport failures and an open circuit breaker.
            $this->logger->warning('Rate lookup failed; using flat rate', ['provider' => $providerKey, 'error' => $e->getMessage()]);

            return $this->fallback($request);
        }

        if ($raw->isEmpty()) {
            return $this->fallback($request);
        }

        $priced = $this->markup($salesChannelId)->applyToCollection($raw)->sortedByPrice();

        $this->cache->set($cacheKey, $priced, $this->cacheTtl($salesChannelId));

        return $priced;
    }

    private function markup(?string $salesChannelId): RateMarkup
    {
        $percent = (float) $this->config->get('rateMarkupPercent', 0.0, $salesChannelId);
        $handling = (float) $this->config->get('rateHandlingFee', 0.0, $salesChannelId);

        // Handling fee is entered in major units; store as minor. Assumes the
        // handling fee shares the quote currency (the sales channel currency).
        return new RateMarkup($percent, (int) round($handling * 100));
    }

    private function fallback(RateRequest $request): RateQuoteCollection
    {
        $flat = (float) $this->config->get('flatRateFallback', 0.0, null);

        return new RateQuoteCollection(new RateQuote(
            providerKey: 'fallback',
            serviceCode: 'flat',
            serviceName: 'Standard shipping',
            amount: Money::fromMajor((string) $flat, $request->currency),
        ));
    }

    private function cacheTtl(?string $salesChannelId): int
    {
        $ttl = (int) $this->config->get('rateCacheTtlSeconds', 900, $salesChannelId);

        return $ttl > 0 ? $ttl : 900;
    }
}
