<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate\Controller;

use Kommandhub\ShippingSW\Checkout\Rate\CarrierSelectionStore;
use Kommandhub\ShippingSW\Checkout\Rate\CartRateRequestFactory;
use Kommandhub\ShippingSW\Checkout\Rate\RateAggregator;
use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Storefront endpoints backing the checkout carrier picker (requirement 2):
 *  - GET  lists the allow-listed carrier options for the current cart;
 *  - POST records the shopper's chosen carrier into the per-cart session.
 *
 * After a POST the storefront re-triggers cart calculation, at which point
 * LiveRateDeliveryProcessor prices the chosen carrier. Mirrors the Terminal
 * WooCommerce flow (fetch rates → customer picks → session → recalculated cost).
 */

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class CarrierSelectionController extends StorefrontController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CartRateRequestFactory $rateRequestFactory,
        private readonly RateAggregator $aggregator,
        private readonly CarrierSelectionStore $carrierSelection,
    ) {
    }

    #[Route(
        path: '/kommandhub/shipping/carriers',
        name: 'frontend.kommandhub.shipping.carriers',
        defaults: ['XmlHttpRequest' => 'true'],
        methods: ['GET']
    )]
    public function list(SalesChannelContext $context): JsonResponse
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);
        $request = $this->rateRequestFactory->fromCart($cart, $context);
        if (null === $request) {
            return new JsonResponse(['carriers' => [], 'selected' => null]);
        }

        $quotes = $this->aggregator->quote($request, $context->getSalesChannelId());
        $selected = $this->carrierSelection->get($context->getToken());

        $carriers = array_map(static fn (RateQuote $q): array => [
            'carrierCode' => $q->carrierCode,
            'carrierName' => $q->carrierName,
            'serviceName' => $q->serviceName,
            'amount' => $q->amount->major(),
            'currency' => $q->amount->currency->value,
            'estimatedDaysMin' => $q->estimatedDaysMin,
            'estimatedDaysMax' => $q->estimatedDaysMax,
            'description' => $q->description,
            'logoUrl' => $q->logoUrl,
        ], $quotes->all());

        return new JsonResponse(['carriers' => $carriers, 'selected' => $selected]);
    }

    #[Route(
        path: '/kommandhub/shipping/select-carrier',
        name: 'frontend.kommandhub.shipping.select_carrier',
        defaults: ['XmlHttpRequest' => 'true'],
        methods: ['POST'],
    )]
    public function select(Request $request, SalesChannelContext $context): JsonResponse
    {
        $carrierCode = (string) $request->request->get('carrierCode', '');
        $this->carrierSelection->set($context->getToken(), '' === $carrierCode ? null : $carrierCode);

        return new JsonResponse(['status' => 'ok']);
    }
}
