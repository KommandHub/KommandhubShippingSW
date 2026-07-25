<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

/**
 * Remembers which carrier the customer picked for a cart, between the storefront
 * click and the delivery recalculation. Behind an interface so the pure
 * pricing decision (selected vs cheapest) is testable without a session.
 */
interface CarrierSelectionStore
{
    public function get(string $cartToken): ?string;

    public function set(string $cartToken, ?string $carrierCode): void;
}
