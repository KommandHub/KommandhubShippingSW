<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Rate;

use Kommandhub\ShippingSW\Model\ValueObject\Money;

/**
 * One priced delivery option from one provider. serviceCode is the value the
 * shipment-creation call feeds back to the provider; providerKey ties the
 * quote to the adapter that produced it so the checkout knows who to book with.
 */
final readonly class RateQuote
{
    public function __construct(
        public string $providerKey,
        public string $serviceCode,
        public string $serviceName,
        public Money $amount,
        public ?int $estimatedDaysMin = null,
        public ?int $estimatedDaysMax = null,
        public ?string $carrierName = null,
    ) {
    }
}
