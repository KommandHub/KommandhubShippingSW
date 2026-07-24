<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Entity\TrackingEvent;

use Kommandhub\ShippingSW\Entity\Shipment\ShipmentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * An immutable audit row per parcel scan/status change. Both webhook pushes and
 * scheduled polls append these; the canonical status column drives the Shopware
 * delivery state machine. occurred_at is the carrier's event time, distinct
 * from created_at (when we recorded it).
 */
class ShipmentTrackingEventDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'kommandhub_shipping_tracking_event';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ShipmentTrackingEventEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ShipmentTrackingEventCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('shipment_id', 'shipmentId', ShipmentDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new StringField('provider_key', 'providerKey'))->addFlags(new ApiAware(), new Required()),
            (new StringField('tracking_number', 'trackingNumber'))->addFlags(new ApiAware()),
            (new StringField('status', 'status'))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('occurred_at', 'occurredAt'))->addFlags(new ApiAware(), new Required()),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware()),
            (new StringField('location', 'location'))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('shipment', 'shipment_id', ShipmentDefinition::class, 'id'),
            new CreatedAtField(),
        ]);
    }
}
