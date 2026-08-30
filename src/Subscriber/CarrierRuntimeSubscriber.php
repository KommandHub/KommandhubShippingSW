<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Subscriber;

use Kommandhub\ShippingSW\Entity\Carrier\CarrierDefinition;
use Kommandhub\ShippingSW\Entity\Carrier\CarrierEntity;
use Kommandhub\ShippingSW\Provider\Carrier\CarrierDataHydratorRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Populates each loaded carrier's runtime $data with a typed CarrierData struct,
 * built from its stored provider_data by the matching provider hydrator. This is
 * the "runtime property" half of Shopware's JSON+Runtime pattern: storage is a
 * JsonField, access is a strongly-typed struct — and the mapping lives in the
 * provider layer, not here.
 */
final class CarrierRuntimeSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CarrierDataHydratorRegistry $hydrators)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CarrierDefinition::ENTITY_NAME . '.loaded' => 'onCarriersLoaded',
        ];
    }

    public function onCarriersLoaded(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $entity) {
            if (!$entity instanceof CarrierEntity) {
                continue;
            }

            $entity->setData($this->hydrators->hydrate($entity->getProviderKey(), $entity->getProviderData()));
        }
    }
}
