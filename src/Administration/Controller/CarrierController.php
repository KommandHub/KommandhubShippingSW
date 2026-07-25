<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Administration\Controller;

use Kommandhub\ShippingSW\Model\Carrier\Carrier;
use Kommandhub\ShippingSW\Provider\Capability;
use Kommandhub\ShippingSW\Provider\ProviderContextFactory;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API that powers the "allowed carriers" config component: given the
 * provider the owner selected (and the sales channel), it returns that
 * provider's carrier catalogue for the owner to filter down.
 *
 * `_acl: shipping.manage` is the real gate.
 */
#[Route(defaults: ['_routeScope' => ['api'], '_acl' => ['shipping.manage']])]
class CarrierController
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderContextFactory $contextFactory,
    ) {
    }

    #[Route(
        path: '/api/_action/kommandhub-shipping/carriers',
        name: 'api.action.kommandhub_shipping.carriers',
        methods: ['GET'],
    )]
    public function list(Request $request): JsonResponse
    {
        $providerKey = (string) $request->query->get('provider', '');
        $salesChannelId = $request->query->get('salesChannelId');
        $salesChannelId = \is_string($salesChannelId) && '' !== $salesChannelId ? $salesChannelId : null;

        if ('' === $providerKey || !$this->registry->has($providerKey)) {
            return new JsonResponse(['carriers' => []]);
        }

        $provider = $this->registry->get($providerKey);
        if (!$provider->supports(Capability::LIST_CARRIERS)) {
            return new JsonResponse(['carriers' => []]);
        }

        try {
            $carriers = $provider->listCarriers($this->contextFactory->forSalesChannel($salesChannelId));
        } catch (\Throwable $e) {
            return new JsonResponse(['carriers' => [], 'error' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['carriers' => array_map(static fn (Carrier $c): array => [
            'code' => $c->code,
            'name' => $c->name,
            'logoUrl' => $c->logoUrl,
        ], $carriers->all())]);
    }
}
