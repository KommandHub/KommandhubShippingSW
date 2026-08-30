<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Administration\Controller;

use Kommandhub\ShippingSW\Model\Carrier\Carrier;
use Kommandhub\ShippingSW\Model\Carrier\CarrierCollection;
use Kommandhub\ShippingSW\Provider\Catalog\CarrierCatalog;
use Kommandhub\ShippingSW\Provider\Catalog\CarrierCatalogSynchronizer;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API powering the "allowed carriers" config component.
 *
 *  - GET  reads the prefetched catalogue from the DB (no provider API call, so
 *         the config page loads instantly).
 *  - POST refresh re-syncs one provider on demand (the "Refresh" button), for
 *         when the owner just added credentials or a carrier changed.
 *
 * `_acl: shipping.manage` is the real gate.
 */
#[Route(defaults: ['_routeScope' => ['api'], '_acl' => ['shipping.manage']])]
class CarrierController
{
    public function __construct(
        private readonly CarrierCatalog $catalog,
        private readonly CarrierCatalogSynchronizer $synchronizer,
    ) {
    }

    #[Route(
        path: '/api/_action/kommandhub-shipping/carriers',
        name: 'api.action.kommandhub_shipping.carriers',
        methods: ['GET'],
    )]
    public function list(Request $request, Context $context): JsonResponse
    {
        $providerKey = (string) $request->query->get('provider', '');
        if ('' === $providerKey) {
            return new JsonResponse(['carriers' => []]);
        }

        return $this->respond($this->catalog->forProvider($providerKey, $context));
    }

    #[Route(
        path: '/api/_action/kommandhub-shipping/carriers/refresh',
        name: 'api.action.kommandhub_shipping.carriers.refresh',
        methods: ['POST'],
    )]
    public function refresh(Request $request, Context $context): JsonResponse
    {
        $providerKey = (string) $request->request->get('provider', '');
        if ('' === $providerKey) {
            return new JsonResponse(['carriers' => []]);
        }

        try {
            $carriers = $this->synchronizer->sync($providerKey, $context);
        } catch (\Throwable $e) {
            return new JsonResponse(['carriers' => [], 'error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return $this->respond($carriers);
    }

    private function respond(CarrierCollection $carriers): JsonResponse
    {
        return new JsonResponse(['carriers' => array_map(static fn (Carrier $c): array => [
            'code' => $c->code,
            'name' => $c->name,
            'logoUrl' => $c->logoUrl,
        ], $carriers->all())]);
    }
}
