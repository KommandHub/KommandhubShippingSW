<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\DataAbstractionLayer;

use Kommandhub\ShippingSW\Entity\Carrier\CarrierEntity;
use Kommandhub\ShippingSW\Model\Carrier\Carrier;
use Kommandhub\ShippingSW\Model\Carrier\CarrierCollection;
use Kommandhub\ShippingSW\Provider\Catalog\CarrierCatalog;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * DAL-backed carrier catalogue over kommandhub_shipping_carrier. Ids are derived
 * from provider_key + code, so replaceForProvider() upserts the same rows and a
 * carrier keeps a stable id across syncs.
 */
final class DalCarrierCatalog implements CarrierCatalog
{
    public function __construct(private readonly EntityRepository $carrierRepository)
    {
    }

    public function forProvider(string $providerKey, Context $context): CarrierCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('providerKey', $providerKey))
            ->addFilter(new EqualsFilter('active', true));

        $carriers = [];
        /** @var CarrierEntity $entity */
        foreach ($this->carrierRepository->search($criteria, $context)->getEntities() as $entity) {
            $carriers[] = new Carrier($entity->getCode(), $entity->getName(), $entity->getLogoUrl(), $entity->getProviderData());
        }

        return new CarrierCollection(...$carriers);
    }

    public function replaceForProvider(string $providerKey, CarrierCollection $carriers, Context $context): void
    {
        $payload = [];
        $keepIds = [];
        foreach ($carriers as $carrier) {
            $id = $this->id($providerKey, $carrier->code);
            $keepIds[] = $id;
            $payload[] = [
                'id' => $id,
                'providerKey' => $providerKey,
                'code' => $carrier->code,
                'name' => $carrier->name,
                'logoUrl' => $carrier->logoUrl,
                'providerData' => $carrier->providerData,
                'active' => true,
            ];
        }

        if ([] !== $payload) {
            $this->carrierRepository->upsert($payload, $context);
        }

        $this->deleteStale($providerKey, $keepIds, $context);
    }

    /**
     * @param list<string> $keepIds
     */
    private function deleteStale(string $providerKey, array $keepIds, Context $context): void
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('providerKey', $providerKey));

        $stale = [];
        foreach ($this->carrierRepository->searchIds($criteria, $context)->getIds() as $id) {
            if (!\in_array($id, $keepIds, true)) {
                $stale[] = ['id' => $id];
            }
        }

        if ([] !== $stale) {
            $this->carrierRepository->delete($stale, $context);
        }
    }

    private function id(string $providerKey, string $code): string
    {
        return Uuid::fromStringToHex($providerKey . ':' . $code);
    }
}
