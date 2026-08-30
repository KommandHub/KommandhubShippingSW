<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Entity\Carrier;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Prefetched carrier catalogue. Each row is one carrier of one provider —
 * provider_key is the "mark" that keeps Terminal's carriers separate from Bob
 * Go's, so the config UI shows the right set for the selected provider without
 * a live API call. Populated by CarrierCatalogSynchronizer (scheduled + on
 * demand), read by the admin carrier endpoint.
 */
class CarrierDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'kommandhub_shipping_carrier';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CarrierEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CarrierCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('provider_key', 'providerKey'))->addFlags(new ApiAware(), new Required()),
            (new StringField('code', 'code'))->addFlags(new ApiAware(), new Required()),
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            (new LongTextField('logo_url', 'logoUrl'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            // Full raw provider payload — richness without schema churn. Read it
            // only through the typed CarrierData struct (see CarrierEntity::$data),
            // never key-by-key in business logic.
            (new JsonField('provider_data', 'providerData'))->addFlags(new ApiAware()),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
