<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Webhook\Controller;

use Kommandhub\ShippingSW\Checkout\Tracking\TrackingPipeline;
use Kommandhub\ShippingSW\Provider\BobGo\BobGoMapper;
use Kommandhub\ShippingSW\Provider\ProviderContextFactory;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bob Go tracking webhook. Different endpoint, different signature header, and a
 * different HMAC algorithm from Terminal — yet once verified it feeds the SAME
 * TrackingPipeline with canonical events. Two providers, two signature schemes,
 * one downstream path: the proof that tracking is provider-agnostic.
 */
#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false, 'auth_required' => false])]
class BobGoWebhookController extends StorefrontController
{
    private const SIGNATURE_HEADER = 'x-bobgo-signature';

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderContextFactory $contextFactory,
        private readonly BobGoMapper $mapper,
        private readonly TrackingPipeline $pipeline,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/kommandhub/shipping/bobgo/webhook',
        name: 'frontend.kommandhub.shipping.bobgo.webhook',
        methods: ['POST'],
    )]
    public function handle(Request $request): Response
    {
        if (!$this->registry->has(BobGoMapper::PROVIDER_KEY)) {
            return new JsonResponse(['error' => 'provider not configured'], Response::HTTP_NOT_FOUND);
        }

        $body = $request->getContent();
        $signature = (string) $request->headers->get(self::SIGNATURE_HEADER, '');
        $provider = $this->registry->get(BobGoMapper::PROVIDER_KEY);
        $context = $this->contextFactory->forSalesChannel(null);

        if (!$provider->verifyWebhook($body, $signature, $context)) {
            $this->logger->warning('Rejected Bob Go webhook: invalid signature');

            return new JsonResponse(['error' => 'invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true) ?: [];
        /** @var array<string, mixed> $data */
        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $trackingNumber = (string) ($data['tracking_reference'] ?? '');

        if ('' === $trackingNumber) {
            return new JsonResponse(['status' => 'ignored'], Response::HTTP_OK);
        }

        $this->pipeline->ingest(
            $this->mapper->toTrackingEvent($data, $trackingNumber),
            Context::createDefaultContext(),
        );

        return new JsonResponse(['status' => 'ok'], Response::HTTP_OK);
    }
}
