<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Storefront-session-backed carrier selection, keyed by cart token so a shopper
 * with an unusual multi-cart setup can't cross wires. Mirrors how the Terminal
 * WooCommerce plugin keeps the chosen rate in the WC session.
 */
final class SessionCarrierSelectionStore implements CarrierSelectionStore
{
    private const PREFIX = 'kommandhub_shipping_carrier_';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function get(string $cartToken): ?string
    {
        $session = $this->requestStack->getSession();
        $value = $session->get(self::PREFIX . $cartToken);

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public function set(string $cartToken, ?string $carrierCode): void
    {
        $session = $this->requestStack->getSession();
        if (null === $carrierCode || '' === $carrierCode) {
            $session->remove(self::PREFIX . $cartToken);

            return;
        }

        $session->set(self::PREFIX . $cartToken, $carrierCode);
    }
}
