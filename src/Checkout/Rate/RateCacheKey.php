<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Checkout\Rate;

use Kommandhub\ShippingSW\Model\Rate\RateRequest;

/**
 * Builds a stable, collision-resistant cache key for a rate lookup. Two rate
 * requests that would receive the same quotes must produce the same key, so the
 * key is derived only from the fields a carrier actually prices on:
 * origin/destination locality, weight, dimensions, currency, declared value —
 * plus the provider and its live/sandbox mode.
 */
final class RateCacheKey
{
    public static function for(RateRequest $request, string $providerKey, bool $sandbox): string
    {
        $parts = [
            'provider' => $providerKey,
            'mode' => $sandbox ? 'sandbox' : 'live',
            'origin' => self::place($request->origin->countryCode, $request->origin->postalCode, $request->origin->city),
            'dest' => self::place($request->destination->countryCode, $request->destination->postalCode, $request->destination->city),
            'weight' => $request->weight->grams,
            'dims' => $request->dimensions->lengthMm . 'x' . $request->dimensions->widthMm . 'x' . $request->dimensions->heightMm,
            'currency' => $request->currency->value,
            'value' => $request->declaredValue?->minorAmount ?? 0,
        ];

        return 'kh_ship_rate_' . hash('sha256', implode('|', array_map(
            static fn (string $k, int|string $v): string => $k . '=' . $v,
            array_keys($parts),
            array_values($parts),
        )));
    }

    private static function place(string $country, ?string $postal, string $city): string
    {
        return strtoupper($country) . ':' . strtoupper($postal ?? '') . ':' . strtoupper($city);
    }
}
