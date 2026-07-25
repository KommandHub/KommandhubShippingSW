<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

use Kommandhub\ShippingSW\Util\ShippingConstants;
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
 * Decorates the core DeliveryProcessor to price the delivery from a live,
 * provider-sourced rate — but ONLY for our gate shipping method. The core runs
 * first (baseline always exists); then, if the cart's selected method is ours,
 * we price the customer's chosen carrier (or cheapest allowed if none picked)
 * from the aggregator, which already applied the owner's allow-list and
 * guarantees a flat-rate fallback.
 *
 * Degrades silently to the core price when the method isn't ours, the currency
 * is unsupported, or there's no shipping address yet — checkout never breaks.
 */
final class LiveRateDeliveryProcessor implements CartProcessorInterface, CartDataCollectorInterface
{
    public function __construct(
        private readonly CartProcessorInterface&CartDataCollectorInterface $decorated,
        private readonly RateAggregator $aggregator,
        private readonly CartRateRequestFactory $rateRequestFactory,
        private readonly CarrierSelectionStore $carrierSelection,
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

        // Activation gate (requirement 1): provider logic runs ONLY when the
        // cart's selected shipping method is ours. Self-pickup, flat-rate, and
        // every other method keep the core price and never trigger a provider.
        if ($context->getShippingMethod()->getTechnicalName() !== ShippingConstants::SHIPPING_METHOD_TECHNICAL_NAME) {
            return;
        }

        $request = $this->rateRequestFactory->fromCart($toCalculate, $context);
        if (null === $request) {
            return;
        }

        try {
            // Already allow-list filtered + priced + sorted by the aggregator.
            $quotes = $this->aggregator->quote($request, $context->getSalesChannelId());
        } catch (\Throwable $e) {
            $this->logger->warning('Live rating skipped; keeping core delivery price', ['error' => $e->getMessage()]);

            return;
        }

        // The customer's chosen carrier if they picked one, else cheapest allowed.
        $selectedCode = $this->carrierSelection->get($original->getToken());
        $chosen = null !== $selectedCode ? $quotes->firstWithCarrierCode($selectedCode) : null;
        $chosen ??= $quotes->cheapest();

        if (null === $chosen) {
            return;
        }

        $amount = $chosen->amount->minorAmount / $chosen->amount->currency->subunitFactor();
        $price = new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection());

        foreach ($toCalculate->getDeliveries() as $delivery) {
            $delivery->setShippingCosts($price);
        }
    }
}
