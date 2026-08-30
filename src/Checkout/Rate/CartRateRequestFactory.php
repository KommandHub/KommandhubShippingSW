<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

use Kommandhub\ShippingSW\Model\Rate\RateRequest;
use Kommandhub\ShippingSW\Model\ValueObject\Address;
use Kommandhub\ShippingSW\Model\ValueObject\Currency;
use Kommandhub\ShippingSW\Model\ValueObject\Dimensions;
use Kommandhub\ShippingSW\Model\ValueObject\Weight;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Builds a canonical RateRequest from the current cart + sales-channel context.
 * Shared by the delivery processor (pricing) and the storefront carrier widget
 * (listing options), so both rate against exactly the same request.
 *
 * Returns null when a rate can't be built (unsupported currency, no shipping
 * address yet) — callers treat null as "skip live rating".
 *
 * ponytail: origin is a warehouse placeholder and parcel dimensions are nominal
 * — both are merchant configuration to wire before go-live (TODO).
 */
final class CartRateRequestFactory
{
    public function fromCart(Cart $cart, SalesChannelContext $context): ?RateRequest
    {
        $currency = Currency::tryFrom($context->getCurrency()->getIsoCode());
        if (null === $currency) {
            return null;
        }

        $shippingAddress = $context->getShippingLocation()->getAddress();
        if (null === $shippingAddress || null === $shippingAddress->getCountry()) {
            return null;
        }

        $countryIso = (string) $shippingAddress->getCountry()->getIso();

        $destination = new Address(
            countryCode: $countryIso,
            city: (string) $shippingAddress->getCity(),
            line1: (string) $shippingAddress->getStreet(),
            state: $shippingAddress->getCountryState()?->getTranslation('name'),
            postalCode: $shippingAddress->getZipcode(),
        );

        return new RateRequest(
            origin: new Address(countryCode: $countryIso, city: 'Warehouse', line1: 'Warehouse'), // TODO: warehouse origin
            destination: $destination,
            weight: Weight::fromGrams($this->cartWeightGrams($cart)),
            dimensions: Dimensions::fromCentimeters(30, 20, 10), // TODO: real parcel packing
            currency: $currency,
            itemNames: $this->itemNames($cart),
        );
    }

    /**
     * Product labels in the cart, for a provider parcel description.
     *
     * @return list<string>
     */
    private function itemNames(Cart $cart): array
    {
        $names = [];
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $label = $lineItem->getLabel();
            if (null !== $label && '' !== $label) {
                $names[] = $label;
            }
        }

        return array_values(array_unique($names));
    }

    private function cartWeightGrams(Cart $cart): int
    {
        $kg = 0.0;
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $weight = $lineItem->getDeliveryInformation()?->getWeight();
            if (null !== $weight) {
                $kg += $weight * $lineItem->getQuantity();
            }
        }

        return (int) round($kg * 1000);
    }
}
