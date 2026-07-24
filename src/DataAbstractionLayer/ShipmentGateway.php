<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\DataAbstractionLayer;

use Kommandhub\ShippingSW\Entity\Shipment\ShipmentEntity;
use Kommandhub\ShippingSW\Model\Shipment\Shipment;
use Kommandhub\ShippingSW\Model\Shipment\ShipmentRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * All shipment persistence in one place, so handlers speak intent ("save this
 * booking") rather than DAL criteria. The primary key is derived from the
 * idempotency key, which makes save() an upsert: a retried job writes the same
 * row instead of a second booking.
 */
final class ShipmentGateway
{
    public function __construct(private readonly EntityRepository $shipmentRepository)
    {
    }

    public function findByIdempotencyKey(string $idempotencyKey, Context $context): ?ShipmentEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('idempotencyKey', $idempotencyKey));

        /** @var ShipmentEntity|null $entity */
        $entity = $this->shipmentRepository->search($criteria, $context)->first();

        return $entity;
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
