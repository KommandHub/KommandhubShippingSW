<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Installer;

use Kommandhub\ShippingSW\Util\ShippingConstants;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Creates (and toggles) the plugin's gate shipping method — the one method that
 * switches provider logic on for a cart. Idempotent: the id is derived from the
 * technicalName, so install/update upserts the same row.
 *
 * ponytail: leaves availabilityRule and delivery time to Shopware defaults and
 * does not auto-assign the method to sales channels — that is a merchant setup
 * step (Settings ▸ Shipping). Marked TODO; wire an assignment if onboarding
 * should be zero-click.
 */
final class ShippingMethodInstaller
{
    public function __construct(
        private readonly EntityRepository $shippingMethodRepository,
        private readonly EntityRepository $deliveryTimeRepository,
    ) {
    }

    public function install(Context $context): void
    {
        $id = Uuid::fromStringToHex(ShippingConstants::SHIPPING_METHOD_TECHNICAL_NAME);

        $data = [
            'id' => $id,
            'technicalName' => ShippingConstants::SHIPPING_METHOD_TECHNICAL_NAME,
            'name' => 'Live carrier rates',
            'active' => true,
            'taxType' => 'auto',
            'deliveryTimeId' => $this->anyDeliveryTimeId($context),
            'prices' => [[
                // A zero base price satisfies the schema; the real amount is set
                // per-cart by LiveRateDeliveryProcessor.
                'calculation' => 1,
                'quantityStart' => 1,
                'currencyPrice' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'net' => 0.0,
                    'gross' => 0.0,
                    'linked' => false,
                ]],
            ]],
        ];

        $this->shippingMethodRepository->upsert([$data], $context);
    }

    public function setActive(bool $active, Context $context): void
    {
        $id = $this->existingId($context);
        if (null === $id) {
            return;
        }

        $this->shippingMethodRepository->update([['id' => $id, 'active' => $active]], $context);
    }

    private function existingId(Context $context): ?string
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('technicalName', ShippingConstants::SHIPPING_METHOD_TECHNICAL_NAME),
        );

        return $this->shippingMethodRepository->searchIds($criteria, $context)->firstId();
    }

    private function anyDeliveryTimeId(Context $context): ?string
    {
        return $this->deliveryTimeRepository->searchIds(new Criteria(), $context)->firstId();
    }
}
