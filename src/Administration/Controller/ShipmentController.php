<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Administration\Controller;

use Kommandhub\ShippingSW\Checkout\Shipment\Message\CreateShipmentMessage;
use Kommandhub\ShippingSW\Checkout\Shipment\Message\GenerateLabelMessage;
use Kommandhub\ShippingSW\Checkout\Shipment\OrderShipmentRequestFactory;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API for shipment fulfilment. Both actions are async: the endpoint
 * validates + enqueues and returns 202, so the admin UI never blocks on a
 * courier API, and the Messenger job carries the idempotency key (create) or
 * the shipment id (label) so retries are safe.
 *
 * `_acl: shipping.manage` gates the routes server-side — the admin-side ACL is
 * decoration; this is the real check.
 */
#[Route(defaults: ['_routeScope' => ['api'], '_acl' => ['shipping.manage']])]
class ShipmentController
{
    public function __construct(
        private readonly OrderShipmentRequestFactory $requestFactory,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route(
        path: '/api/_action/kommandhub-shipping/order/{orderId}/create-shipment',
        name: 'api.action.kommandhub_shipping.create_shipment',
        methods: ['POST'],
    )]
    public function createShipment(string $orderId, Request $request): JsonResponse
    {
        $serviceCode = (string) $request->request->get('serviceCode', '');
        if ('' === $serviceCode) {
            return new JsonResponse(['error' => 'serviceCode is required'], Response::HTTP_BAD_REQUEST);
        }

        $shipmentRequest = $this->requestFactory->fromOrderId($orderId, $serviceCode, Context::createDefaultContext());
        if (null === $shipmentRequest) {
            return new JsonResponse(
                ['error' => 'Cannot build a shipment for this order (missing provider, delivery, or address).'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->bus->dispatch(new CreateShipmentMessage($shipmentRequest, $orderId, null));

        return new JsonResponse(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }

    #[Route(
        path: '/api/_action/kommandhub-shipping/shipment/{shipmentId}/label',
        name: 'api.action.kommandhub_shipping.generate_label',
        methods: ['POST'],
    )]
    public function generateLabel(string $shipmentId): JsonResponse
    {
        $this->bus->dispatch(new GenerateLabelMessage($shipmentId));

        return new JsonResponse(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }
}
