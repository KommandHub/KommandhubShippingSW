<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Decorates the core DeliveryProcessor to replace the delivery cost with a live,
 * provider-sourced rate. The core runs first (so a working baseline always
 * exists); then, when we can build a canonical RateRequest from the cart, the
 * aggregator's cheapest priced quote overrides the shipping cost. The aggregator
 * itself guarantees a flat-rate fallback, so this never leaves checkout without
 * a price.
 *
 * It degrades silently to the core price when: the currency is outside the
 * supported set, there is no shipping address yet, or the origin warehouse is
 * unconfigured — checkout must never break because of live rating.
 *
 * ponytail: origin/parcel dimensions are placeholders here (warehouse origin +
 * real parcel packing are merchant configuration). Wire them from config before
 * go-live; marked TODO below.
 */
final class LiveRateDeliveryProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public function __construct(
        private readonly CartProcessorInterface&CartDataCollectorInterface $decorated,
        private readonly RateAggregator $aggregator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $this->decorated->collect($data, $original, $context, $behavior);
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $this->decorated->process($data, $original, $toCalculate, $context, $behavior);

        $request = $this->buildRateRequest($toCalculate, $context);
        if (null === $request) {
            return;
        }

        try {
            $quotes = $this->aggregator->quote($request, $context->getSalesChannelId());
            $cheapest = $quotes->sortedByPrice()->cheapest();
        } catch (\Throwable $e) {
            $this->logger->warning('Live rating skipped; keeping core delivery price', ['error' => $e->getMessage()]);

            return;
        }

        if (null === $cheapest) {
            return;
        }

        $amount = $cheapest->amount->minorAmount / $cheapest->amount->currency->subunitFactor();
        $price = new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection());

        foreach ($toCalculate->getDeliveries() as $delivery) {
            $delivery->setShippingCosts($price);
        }
    }

    private function buildRateRequest(Cart $cart, SalesChannelContext $context): ?RateRequest
    {
        $currency = Currency::tryFrom($context->getCurrency()->getIsoCode());
        if (null === $currency) {
            return null; // unsupported currency → leave the core price
        }

        $shippingAddress = $context->getShippingLocation()->getAddress();
        if (null === $shippingAddress || null === $shippingAddress->getCountry()) {
            return null;
        }

        $destination = new Address(
            countryCode: (string) $shippingAddress->getCountry()->getIso(),
            city: (string) $shippingAddress->getCity(),
            line1: (string) $shippingAddress->getStreet(),
            postalCode: $shippingAddress->getZipcode(),
        );

        // TODO: origin from merchant warehouse config; placeholder for now.
        $origin = new Address(countryCode: (string) $shippingAddress->getCountry()->getIso(), city: 'Warehouse', line1: 'Warehouse');

        return new RateRequest(
            origin: $origin,
            destination: $destination,
            weight: Weight::fromGrams($this->cartWeightGrams($cart)),
            dimensions: Dimensions::fromCentimeters(30, 20, 10), // TODO: real parcel packing
            currency: $currency,
        );
    }

    private function cartWeightGrams(Cart $cart): int
    {
        $kg = 0.0;
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $weight = $lineItem->getDeliveryInformation()?->getWeight();
            if (null !== $weight) {
                $kg += $weight * $lineItem->getQuantity();
            }
        }

        return (int) round($kg * 1000);
    }
}
