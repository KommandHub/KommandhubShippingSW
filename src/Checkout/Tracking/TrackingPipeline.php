<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Tracking;

use Kommandhub\ShippingSW\DataAbstractionLayer\ShipmentGateway;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEvent;
use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Kommandhub\ShippingSW\Util\ShippingConstants;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

/**
 * The single, provider-agnostic destination for every tracking update, whether
 * it arrived by webhook or by the reconciliation poll. It records the event,
 * mirrors the canonical status onto the shipment, writes tracking metadata onto
 * the order delivery, and advances the Shopware delivery state machine.
 *
 * Because it consumes canonical TrackingEvent only, Terminal, Bob Go and any
 * future provider all flow through this one path unchanged.
 */
final class TrackingPipeline
{
    /**
     * Canonical status -> order_delivery state-machine transition action. Only
     * the transitions Shopware models are mapped; the rest update metadata only.
     */
    private const TRANSITIONS = [
        TrackingStatus::IN_TRANSIT->value => 'ship',
        TrackingStatus::OUT_FOR_DELIVERY->value => 'ship',
        TrackingStatus::DELIVERED->value => 'ship',
        TrackingStatus::RETURNED->value => 'retour',
        TrackingStatus::CANCELLED->value => 'cancel',
    ];

    public function __construct(
        private readonly ShipmentGateway $shipmentGateway,
        private readonly EntityRepository $orderDeliveryRepository,
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function ingest(TrackingEvent $event, Context $context): void
    {
        $shipment = $this->shipmentGateway->findByTrackingNumber($event->trackingNumber, $context);
        if (null === $shipment) {
            $this->logger->info('Tracking event for unknown shipment; ignoring', ['trackingNumber' => $event->trackingNumber]);

            return;
        }

        $this->shipmentGateway->appendTrackingEvent($shipment->getId(), $event, $context);

        $orderId = $shipment->getOrderId();
        if (null === $orderId) {
            return;
        }

        $this->writeDeliveryMetadata($orderId, $event, $context);
        $this->advanceDeliveryState($orderId, $event->status, $context);
    }

    private function writeDeliveryMetadata(string $orderId, TrackingEvent $event, Context $context): void
    {
        $deliveryId = $this->findDeliveryId($orderId, $context);
        if (null === $deliveryId) {
            return;
        }

        $this->orderDeliveryRepository->update([[
            'id' => $deliveryId,
            'customFields' => [
                ShippingConstants::CUSTOM_FIELD_TRACKING_NUMBER => $event->trackingNumber,
                ShippingConstants::CUSTOM_FIELD_STATUS => $event->status->value,
                ShippingConstants::CUSTOM_FIELD_CARRIER => $event->providerKey,
            ],
        ]], $context);
    }

    private function advanceDeliveryState(string $orderId, TrackingStatus $status, Context $context): void
    {
        $action = self::TRANSITIONS[$status->value] ?? null;
        if (null === $action) {
            return;
        }

        $deliveryId = $this->findDeliveryId($orderId, $context);
        if (null === $deliveryId) {
            return;
        }

        try {
            $this->stateMachineRegistry->transition(
                new Transition(
                    OrderDeliveryStates::STATE_MACHINE,
                    $deliveryId,
                    $action,
                    'stateId',
                ),
                $context,
            );
        } catch (IllegalTransitionException $e) {
            // Already in the target state, or the transition isn't valid from
            // here (e.g. a late "in transit" after "shipped"). Not an error.
            $this->logger->debug('Delivery state transition skipped', [
                'orderId' => $orderId,
                'action' => $action,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    private function findDeliveryId(string $orderId, Context $context): ?string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->setLimit(1);

        $id = $this->orderDeliveryRepository->searchIds($criteria, $context)->firstId();

        return $id;
    }
}
