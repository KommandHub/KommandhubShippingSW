<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Entity\Carrier;

use Kommandhub\ShippingSW\Provider\Carrier\CarrierData;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CarrierEntity extends Entity
{
    use EntityIdTrait;

    protected string $providerKey;

    protected string $code;

    protected string $name;

    protected ?string $logoUrl = null;

    protected bool $active = true;

    /** @var array<string, mixed> raw provider payload (persisted JSON) */
    protected array $providerData = [];

    /**
     * Typed view over $providerData, computed at load time by
     * CarrierRuntimeSubscriber (Shopware's runtime-property pattern). Not a DAL
     * column — never written.
     */
    protected ?CarrierData $data = null;

    public function getProviderKey(): string
    {
        return $this->providerKey;
    }

    public function setProviderKey(string $providerKey): void
    {
        $this->providerKey = $providerKey;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): void
    {
        $this->logoUrl = $logoUrl;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /** @return array<string, mixed> */
    public function getProviderData(): array
    {
        return $this->providerData;
    }

    /** @param array<string, mixed> $providerData */
    public function setProviderData(array $providerData): void
    {
        $this->providerData = $providerData;
    }

    public function getData(): ?CarrierData
    {
        return $this->data;
    }

    public function setData(?CarrierData $data): void
    {
        $this->data = $data;
    }
}
