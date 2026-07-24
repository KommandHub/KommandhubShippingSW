<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Entity\Shipment;

use Kommandhub\ShippingSW\Entity\TrackingEvent\ShipmentTrackingEventCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ShipmentEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $orderId = null;

    protected ?string $salesChannelId = null;

    protected string $providerKey;

    protected string $providerShipmentId;

    protected ?string $serviceCode = null;

    protected string $status;

    protected ?string $trackingNumber = null;

    protected ?string $trackingUrl = null;

    protected ?string $reference = null;

    protected string $idempotencyKey;

    protected ?ShipmentTrackingEventCollection $trackingEvents = null;

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getProviderKey(): string
    {
        return $this->providerKey;
    }

    public function setProviderKey(string $providerKey): void
    {
        $this->providerKey = $providerKey;
    }

    public function getProviderShipmentId(): string
    {
        return $this->providerShipmentId;
    }

    public function setProviderShipmentId(string $providerShipmentId): void
    {
        $this->providerShipmentId = $providerShipmentId;
    }

    public function getServiceCode(): ?string
    {
        return $this->serviceCode;
    }

    public function setServiceCode(?string $serviceCode): void
    {
        $this->serviceCode = $serviceCode;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): void
    {
        $this->trackingNumber = $trackingNumber;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function setTrackingUrl(?string $trackingUrl): void
    {
        $this->trackingUrl = $trackingUrl;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(string $idempotencyKey): void
    {
        $this->idempotencyKey = $idempotencyKey;
    }

    public function getTrackingEvents(): ?ShipmentTrackingEventCollection
    {
        return $this->trackingEvents;
    }

    public function setTrackingEvents(?ShipmentTrackingEventCollection $trackingEvents): void
    {
        $this->trackingEvents = $trackingEvents;
    }
}
