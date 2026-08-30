<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Shipment\Handler;

use Kommandhub\ShippingSW\Checkout\Shipment\Message\CreateShipmentMessage;
use Kommandhub\ShippingSW\DataAbstractionLayer\ShipmentGateway;
use Kommandhub\ShippingSW\Provider\ProviderContextResolver;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Books a shipment asynchronously. Idempotent on the request's idempotency key
 * twice over: an early lookup skips a repeat, and the gateway's key-derived
 * primary key makes the write itself an upsert. A provider failure is allowed
 * to bubble so Messenger's retry policy re-runs the (idempotent) job.
 */
#[AsMessageHandler]
final class CreateShipmentHandler
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderContextResolver $contextFactory,
        private readonly ShipmentGateway $gateway,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateShipmentMessage $message): void
    {
        $context = Context::createDefaultContext();
        $request = $message->request;

        if (null !== $this->gateway->findByIdempotencyKey($request->idempotencyKey, $context)) {
            $this->logger->info('Shipment already booked; skipping', ['idempotencyKey' => $request->idempotencyKey]);

            return;
        }

        $provider = $this->registry->get($request->providerKey);
        $providerContext = $this->contextFactory->forProvider($message->request->providerKey, $message->salesChannelId);

        $shipment = $provider->createShipment($request, $providerContext);

        $this->gateway->save($shipment, $request, $message->orderId, $message->salesChannelId, $context);

        $this->logger->info('Shipment booked', [
            'provider' => $shipment->providerKey,
            'providerShipmentId' => $shipment->providerShipmentId,
            'orderId' => $message->orderId,
        ]);
    }
}
