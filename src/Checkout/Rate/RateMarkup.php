<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;
use Kommandhub\ShippingSW\Model\ValueObject\Money;

/**
 * Merchant pricing rules applied to raw carrier quotes: a percentage uplift plus
 * a flat handling fee (in minor units of the quote currency). Pure and total —
 * no config, no I/O — so the pricing maths is trivially testable.
 */
final readonly class RateMarkup
{
    public function __construct(
        public float $percent = 0.0,
        public int $handlingFeeMinor = 0,
    ) {
    }

    public function applyToCollection(RateQuoteCollection $quotes): RateQuoteCollection
    {
        return $quotes->map(fn (RateQuote $quote): RateQuote => $this->applyTo($quote));
    }

    public function applyTo(RateQuote $quote): RateQuote
    {
        $base = $quote->amount->minorAmount;
        $marked = (int) round($base * (1 + $this->percent / 100)) + $this->handlingFeeMinor;

        return new RateQuote(
            providerKey: $quote->providerKey,
            serviceCode: $quote->serviceCode,
            serviceName: $quote->serviceName,
            amount: Money::fromMinor($marked, $quote->amount->currency),
            estimatedDaysMin: $quote->estimatedDaysMin,
            estimatedDaysMax: $quote->estimatedDaysMax,
            carrierName: $quote->carrierName,
            carrierCode: $quote->carrierCode,
        );
    }
}
