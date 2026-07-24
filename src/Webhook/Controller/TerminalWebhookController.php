<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Webhook\Controller;

use Kommandhub\ShippingSW\Checkout\Tracking\TrackingPipeline;
use Kommandhub\ShippingSW\Provider\ProviderContextFactory;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Kommandhub\ShippingSW\Provider\TerminalAfrica\TShipMapper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Terminal Africa tracking webhook. Authenticity is the HMAC signature (verified
 * by the adapter); once authentic, the payload is mapped to a canonical
 * TrackingEvent and handed to the shared TrackingPipeline — the exact path a
 * poll or any other provider's webhook uses.
 *
 * Returns 200 for authentic-but-irrelevant events so Terminal stops retrying;
 * only a bad signature is rejected.
 *
 * ponytail: the webhook is not sales-channel scoped, so it verifies against the
 * global (default) webhook secret. Per-channel secrets would need the payload to
 * carry a channel hint — revisit if a merchant runs Terminal on several channels
 * with different secrets.
 */
#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false, 'auth_required' => false])]
class TerminalWebhookController extends StorefrontController
{
    private const SIGNATURE_HEADER = 'x-terminal-signature';

    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderContextFactory $contextFactory,
        private readonly TShipMapper $mapper,
        private readonly TrackingPipeline $pipeline,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/kommandhub/shipping/terminal/webhook',
        name: 'frontend.kommandhub.shipping.terminal.webhook',
        methods: ['POST'],
    )]
    public function handle(Request $request): Response
    {
        if (!$this->registry->has(TShipMapper::PROVIDER_KEY)) {
            return new JsonResponse(['error' => 'provider not configured'], Response::HTTP_NOT_FOUND);
        }

        $body = $request->getContent();
        $signature = (string) $request->headers->get(self::SIGNATURE_HEADER, '');
        $provider = $this->registry->get(TShipMapper::PROVIDER_KEY);
        $context = $this->contextFactory->forSalesChannel(null);

        if (!$provider->verifyWebhook($body, $signature, $context)) {
            $this->logger->warning('Rejected Terminal webhook: invalid signature');

            return new JsonResponse(['error' => 'invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true) ?: [];
        /** @var array<string, mixed> $data */
        $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $trackingNumber = (string) ($data['tracking_number'] ?? '');

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
