<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Entity\Shipment;

use Kommandhub\ShippingSW\Entity\TrackingEvent\ShipmentTrackingEventDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Persisted shipment record. This is a plugin-owned mirror of what a provider
 * booked — the canonical status/tracking fields the admin and the delivery
 * state machine read, independent of any provider's own store.
 *
 * order_id / sales_channel_id are stored as loose ids (no DAL association) on
 * purpose: orders are version-aware and a hard FK would drag version handling
 * into every write. Lookups go through the repository by these ids.
 */
class ShipmentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'kommandhub_shipping_shipment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ShipmentEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ShipmentCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new IdField('order_id', 'orderId'))->addFlags(new ApiAware()),
            (new IdField('sales_channel_id', 'salesChannelId'))->addFlags(new ApiAware()),
            (new StringField('provider_key', 'providerKey'))->addFlags(new ApiAware(), new Required()),
            (new StringField('provider_shipment_id', 'providerShipmentId'))->addFlags(new ApiAware(), new Required()),
            (new StringField('service_code', 'serviceCode'))->addFlags(new ApiAware()),
            (new StringField('status', 'status'))->addFlags(new ApiAware(), new Required()),
            (new StringField('tracking_number', 'trackingNumber'))->addFlags(new ApiAware()),
            (new LongTextField('tracking_url', 'trackingUrl'))->addFlags(new ApiAware()),
            (new StringField('reference', 'reference'))->addFlags(new ApiAware()),
            (new StringField('idempotency_key', 'idempotencyKey'))->addFlags(new ApiAware(), new Required()),
            new OneToManyAssociationField('trackingEvents', ShipmentTrackingEventDefinition::class, 'shipment_id'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
