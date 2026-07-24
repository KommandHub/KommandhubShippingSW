<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\DataAbstractionLayer;

use Kommandhub\ShippingSW\Entity\Shipment\ShipmentCollection;
use Kommandhub\ShippingSW\Entity\Shipment\ShipmentEntity;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Kommandhub\ShippingSW\Model\Tracking\TrackingEvent;
use Kommandhub\ShippingSW\Model\Tracking\TrackingStatus;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * All shipment persistence in one place, so handlers speak intent ("save this
 * booking") rather than DAL criteria. The primary key is derived from the
 * idempotency key, which makes save() an upsert: a retried job writes the same
 * row instead of a second booking.
 */
final class ShipmentGateway
{
    public function __construct(
        private readonly EntityRepository $shipmentRepository,
        private readonly EntityRepository $trackingEventRepository,
    ) {
    }

    public function findByIdempotencyKey(string $idempotencyKey, Context $context): ?ShipmentEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('idempotencyKey', $idempotencyKey));

        /** @var ShipmentEntity|null $entity */
        $entity = $this->shipmentRepository->search($criteria, $context)->first();

        return $entity;
    }

    public function findByTrackingNumber(string $trackingNumber, Context $context): ?ShipmentEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('trackingNumber', $trackingNumber));

        /** @var ShipmentEntity|null $entity */
        $entity = $this->shipmentRepository->search($criteria, $context)->first();

        return $entity;
    }

    /** Shipments not yet in a terminal state, for the reconciliation poll. */
    public function findOpen(Context $context): ShipmentCollection
    {
        $criteria = (new Criteria())->addFilter(new NotFilter(NotFilter::CONNECTION_OR, [
            new EqualsAnyFilter('status', [
                TrackingStatus::DELIVERED->value,
                TrackingStatus::CANCELLED->value,
                TrackingStatus::RETURNED->value,
            ]),
        ]));

        /** @var ShipmentCollection $result */
        $result = $this->shipmentRepository->search($criteria, $context)->getEntities();

        return $result;
    }

    /**
     * Append a tracking event to a shipment and advance its canonical status.
     */
    public function appendTrackingEvent(string $shipmentId, TrackingEvent $event, Context $context): void
    {
        $this->trackingEventRepository->create([[
            'id' => Uuid::randomHex(),
            'shipmentId' => $shipmentId,
            'providerKey' => $event->providerKey,
            'trackingNumber' => $event->trackingNumber,
            'status' => $event->status->value,
            'occurredAt' => $event->occurredAt,
            'description' => $event->description,
            'location' => $event->location,
        ]], $context);

        $this->shipmentRepository->update([[
            'id' => $shipmentId,
            'status' => $event->status->value,
        ]], $context);
    }

    public function getById(string $id, Context $context): ?ShipmentEntity
    {
        $criteria = (new Criteria([$id]))->addAssociation('trackingEvents');

        /** @var ShipmentEntity|null $entity */
        $entity = $this->shipmentRepository->search($criteria, $context)->first();

        return $entity;
    }

    /**
     * Persist a booked shipment. Returns the stored entity id.
     */
    public function save(
        Shipment $shipment,
        ShipmentRequest $request,
        ?string $orderId,
        ?string $salesChannelId,
        Context $context,
    ): string {
        $id = Uuid::fromStringToHex($request->idempotencyKey);

        $this->shipmentRepository->upsert([[
            'id' => $id,
            'orderId' => $orderId,
            'salesChannelId' => $salesChannelId,
            'providerKey' => $shipment->providerKey,
            'providerShipmentId' => $shipment->providerShipmentId,
            'serviceCode' => $shipment->serviceCode,
            'status' => $shipment->status->value,
            'trackingNumber' => $shipment->trackingNumber,
            'trackingUrl' => $shipment->trackingUrl,
            'reference' => $request->reference,
            'idempotencyKey' => $request->idempotencyKey,
        ]], $context);

        return $id;
    }
}
