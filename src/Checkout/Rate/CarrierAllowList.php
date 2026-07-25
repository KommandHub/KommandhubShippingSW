<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

use Kommandhub\ShippingSW\Model\Rate\RateQuote;
use Kommandhub\ShippingSW\Model\Rate\RateQuoteCollection;

/**
 * The store owner's carrier allow-list. Filters a provider's quotes down to the
 * carriers the owner approved. An EMPTY allow-list means "not configured yet" →
 * allow everything, so a fresh install still shows rates rather than nothing.
 *
 * Pure and total: no config, no I/O — the aggregator reads the allowed codes
 * from config and hands them in.
 */
final readonly class CarrierAllowList
{
    /** @var list<string> */
    private array $allowedCodes;

    /**
     * @param list<string> $allowedCodes carrier codes the owner approved
     */
    public function __construct(array $allowedCodes)
    {
        $this->allowedCodes = array_values(array_filter($allowedCodes, static fn ($c): bool => \is_string($c) && '' !== $c));
    }

    public function isUnrestricted(): bool
    {
        return [] === $this->allowedCodes;
    }

    public function allows(?string $carrierCode): bool
    {
        if ($this->isUnrestricted()) {
            return true;
        }

        return null !== $carrierCode && \in_array($carrierCode, $this->allowedCodes, true);
    }

    public function filter(RateQuoteCollection $quotes): RateQuoteCollection
    {
        if ($this->isUnrestricted()) {
            return $quotes;
        }

        return $quotes->filter(fn (RateQuote $quote): bool => $this->allows($quote->carrierCode));
    }
}
