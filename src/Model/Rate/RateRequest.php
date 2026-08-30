<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Rate;

use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Money;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;

/**
 * A request for delivery rates between two addresses for one parcel. This is
 * the cache key input as well: origin + destination + weight + dimensions +
 * currency (+ provider) fully determine a quote set.
 */
final readonly class RateRequest
{
    /**
     * @param list<string> $itemNames labels of the goods being shipped, used to
     *                                 build a human parcel description for
     *                                 providers that require one (e.g. TShip)
     */
    public function __construct(
        public Address $origin,
        public Address $destination,
        public Weight $weight,
        public Dimensions $dimensions,
        public Currency $currency,
        public ?Money $declaredValue = null,
        public array $itemNames = [],
    ) {
    }
}
