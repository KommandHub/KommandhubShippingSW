<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Entity\TrackingEvent;

use Kommandhub\ShippingSW\Entity\Shipment\ShipmentEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ShipmentTrackingEventEntity extends Entity
{
    use EntityIdTrait;

    protected string $shipmentId;

    protected string $providerKey;

    protected ?string $trackingNumber = null;

    protected string $status;

    protected \DateTimeInterface $occurredAt;

    protected ?string $description = null;

    protected ?string $location = null;

    protected ?ShipmentEntity $shipment = null;

    public function getShipmentId(): string
    {
        return $this->shipmentId;
    }

    public function setShipmentId(string $shipmentId): void
    {
        $this->shipmentId = $shipmentId;
    }

    public function getProviderKey(): string
    {
        return $this->providerKey;
    }

    public function setProviderKey(string $providerKey): void
    {
        $this->providerKey = $providerKey;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): void
    {
        $this->trackingNumber = $trackingNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getOccurredAt(): \DateTimeInterface
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeInterface $occurredAt): void
    {
        $this->occurredAt = $occurredAt;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
    }

    public function getShipment(): ?ShipmentEntity
    {
        return $this->shipment;
    }

    public function setShipment(?ShipmentEntity $shipment): void
    {
        $this->shipment = $shipment;
    }
}
