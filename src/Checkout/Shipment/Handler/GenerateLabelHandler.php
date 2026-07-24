<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Shipment\Handler;

use Kommandhub\ShippingSW\Checkout\Shipment\Message\GenerateLabelMessage;
use Kommandhub\ShippingSW\DataAbstractionLayer\ShipmentGateway;
use Kommandhub\ShippingSW\Provider\ProviderContextFactory;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Fetches a shipment's waybill asynchronously via the owning provider.
 */
#[AsMessageHandler]
final class GenerateLabelHandler
{
    public function __construct(
        private readonly ProviderRegistry $registry,
        private readonly ProviderContextFactory $contextFactory,
        private readonly ShipmentGateway $gateway,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateLabelMessage $message): void
    {
        $context = Context::createDefaultContext();

        $shipment = $this->gateway->getById($message->shipmentId, $context);
        if (null === $shipment) {
            $this->logger->warning('Cannot generate label: shipment not found', ['shipmentId' => $message->shipmentId]);

            return;
        }

        $provider = $this->registry->get($shipment->getProviderKey());
        $providerContext = $this->contextFactory->forSalesChannel($shipment->getSalesChannelId());

        $label = $provider->generateLabel($shipment->getProviderShipmentId(), $providerContext);

        // TODO(Phase 1): store $label as an order document (PDF waybill) and
        // attach it to the order; write the storage reference onto the shipment.
        $this->logger->info('Label generated', [
            'shipmentId' => $message->shipmentId,
            'format' => $label->format->value,
        ]);
    }
}
