<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Administration\Controller;

use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Lists the installed shipping providers, so the "active provider" config field
 * is populated from the registry rather than a hardcoded list — installing or
 * removing a provider plugin changes the options with no core edit.
 */
#[Route(defaults: ['_routeScope' => ['api'], '_acl' => ['shipping.manage']])]
class ProviderController
{
    public function __construct(private readonly ProviderRegistry $registry)
    {
    }

    #[Route(
        path: '/api/_action/kommandhub-shipping/providers',
        name: 'api.action.kommandhub_shipping.providers',
        methods: ['GET'],
    )]
    public function list(): JsonResponse
    {
        $providers = array_map(
            static fn (string $key): array => ['key' => $key],
            array_keys($this->registry->all()),
        );

        return new JsonResponse(['providers' => $providers]);
    }
}
