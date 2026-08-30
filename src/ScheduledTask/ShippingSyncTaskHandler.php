<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\ScheduledTask;

use Kommandhub\ShippingSW\Checkout\Tracking\TrackingPipeline;
use Kommandhub\ShippingSW\DataAbstractionLayer\ShipmentGateway;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Provider\Exception\ProviderException;
use Kommandhub\ShippingSW\Provider\ProviderContextResolver;
use Kommandhub\ShippingSW\Provider\ProviderRegistry;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reconciliation poll: the safety net behind webhook-first tracking. Webhooks
 * can be missed (downtime, mis-config); this sweeps every still-open shipment,
 * re-pulls its tracking from the owning provider, and feeds the events through
 * the same TrackingPipeline. Re-ingesting an already-seen event is harmless, so
 * the run is safe to repeat (at-least-once delivery).
 */
#[AsMessageHandler(handles: ShippingSyncTask::class)]
class ShippingSyncTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly ShipmentGateway $shipmentGateway,
        private readonly ProviderRegistry $registry,
        private readonly ProviderContextResolver $contextFactory,
        private readonly TrackingPipeline $pipeline,
        private readonly LoggerInterface $pluginLogger,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();
        $open = $this->shipmentGateway->findOpen($context);

        foreach ($open as $shipment) {
            // Track by the provider's shipment id (always present); the tracking
            // number may not exist yet and is provider-specific anyway.
            if ('' === $shipment->getProviderShipmentId() || !$this->registry->has($shipment->getProviderKey())) {
                continue;
            }

            try {
                $provider = $this->registry->get($shipment->getProviderKey());
                $providerContext = $this->contextFactory->forProvider($shipment->getProviderKey(), $shipment->getSalesChannelId());

                $canonical = new Shipment(
                    providerKey: $shipment->getProviderKey(),
                    providerShipmentId: $shipment->getProviderShipmentId(),
                    serviceCode: $shipment->getServiceCode() ?? '',
                    status: TrackingStatus::tryFrom($shipment->getStatus()) ?? TrackingStatus::UNKNOWN,
                    trackingNumber: $shipment->getTrackingNumber(),
                    trackingUrl: $shipment->getTrackingUrl(),
                );

                foreach ($provider->track($canonical, $providerContext) as $event) {
                    $this->pipeline->ingest($event, $context);
                }
            } catch (ProviderException $e) {
                // One provider hiccup must not abort the whole sweep.
                $this->pluginLogger->warning('Tracking poll failed for shipment', [
                    'shipmentId' => $shipment->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->pluginLogger->info('Shipping tracking reconciliation complete', ['open' => $open->count()]);
    }
}
