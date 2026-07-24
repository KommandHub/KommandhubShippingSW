<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Installer;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Creates this plugin's custom field set and its entity relations.
 *
 * Every method is idempotent: the plugin bootstrap runs them on both install
 * and update, so a second run must be a no-op rather than a duplicate.
 *
 * Field names are global across the installation — always prefix them with the
 * plugin slug. Keep the canonical names in one constant class so a rename
 * cannot silently orphan stored data.
 */
class CustomFieldsInstaller
{
    private const CUSTOM_FIELDSET_NAME = 'kommandhub_shipping_fieldset';

    private const CUSTOM_FIELDSET = [
        'name' => self::CUSTOM_FIELDSET_NAME,
        'config' => [
            'label' => [
                'en-GB' => 'Kommandhub Shipping',
                'de-DE' => 'Kommandhub Shipping',
                'fr-FR' => 'Kommandhub Shipping',
                Defaults::LANGUAGE_SYSTEM => 'Kommandhub Shipping',
            ],
        ],
        'customFields' => [
            [
                'name' => 'kommandhub_shipping_reference',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Reference',
                        'de-DE' => 'Referenz',
                        'fr-FR' => 'Référence',
                        Defaults::LANGUAGE_SYSTEM => 'Reference',
                    ],
                    'customFieldPosition' => 1,
                ],
            ],
            [
                'name' => 'kommandhub_shipping_tracking_number',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Tracking number',
                        'de-DE' => 'Sendungsnummer',
                        'fr-FR' => 'Numéro de suivi',
                        Defaults::LANGUAGE_SYSTEM => 'Tracking number',
                    ],
                    'customFieldPosition' => 2,
                ],
            ],
            [
                'name' => 'kommandhub_shipping_tracking_url',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Tracking URL',
                        'de-DE' => 'Sendungsverfolgungs-URL',
                        'fr-FR' => 'URL de suivi',
                        Defaults::LANGUAGE_SYSTEM => 'Tracking URL',
                    ],
                    'customFieldPosition' => 3,
                ],
            ],
            [
                'name' => 'kommandhub_shipping_carrier',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Carrier',
                        'de-DE' => 'Frachtführer',
                        'fr-FR' => 'Transporteur',
                        Defaults::LANGUAGE_SYSTEM => 'Carrier',
                    ],
                    'customFieldPosition' => 4,
                ],
            ],
            [
                'name' => 'kommandhub_shipping_status',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Shipping status',
                        'de-DE' => 'Versandstatus',
                        'fr-FR' => 'Statut d\'expédition',
                        Defaults::LANGUAGE_SYSTEM => 'Shipping status',
                    ],
                    'customFieldPosition' => 5,
                ],
            ],
        ],
    ];

    /**
     * Entities the field set is attached to. Tracking metadata lives on the
     * order delivery; the reference stays available on the transaction too.
     */
    private const RELATED_ENTITIES = [
        OrderTransactionDefinition::ENTITY_NAME,
        OrderDeliveryDefinition::ENTITY_NAME,
    ];

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository
    ) {
    }

    public function install(Context $context): void
    {
        if ($this->getCustomFieldSetIds($context) !== []) {
            return;
        }

        $this->customFieldSetRepository->upsert([self::CUSTOM_FIELDSET], $context);
    }

    public function addRelations(Context $context): void
    {
        $relationsToInsert = [];

        foreach ($this->getCustomFieldSetIds($context) as $customFieldSetId) {
            foreach (self::RELATED_ENTITIES as $entityName) {
                if ($this->relationExists($context, $customFieldSetId, $entityName)) {
                    continue;
                }

                $relationsToInsert[] = [
                    'customFieldSetId' => $customFieldSetId,
                    'entityName' => $entityName,
                ];
            }
        }

        if ($relationsToInsert === []) {
            return;
        }

        $this->customFieldSetRelationRepository->upsert($relationsToInsert, $context);
    }

    /**
     * Only called when the merchant did NOT ask to keep user data — deleting the
     * field set deletes every value stored in it.
     */
    public function uninstall(Context $context): void
    {
        $ids = $this->getCustomFieldSetIds($context);

        if ($ids === []) {
            return;
        }

        $this->customFieldSetRepository->delete(
            array_map(static fn (string $id) => ['id' => $id], $ids),
            $context
        );
    }

    /**
     * @return string[]
     */
    private function getCustomFieldSetIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        return $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();
    }

    private function relationExists(Context $context, string $customFieldSetId, string $entityName): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFieldSetId', $customFieldSetId));
        $criteria->addFilter(new EqualsFilter('entityName', $entityName));

        return $this->customFieldSetRelationRepository->searchIds($criteria, $context)->getTotal() > 0;
    }
}
