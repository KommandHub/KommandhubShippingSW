<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\ValueObject;

/**
 * Parcel dimensions, canonicalised to millimetres. Volumetric (dimensional)
 * weight is provided as a helper because most carriers bill on the greater of
 * actual and volumetric weight; the divisor differs per carrier, so it is a
 * parameter, defaulting to the common 5000 cm³/kg air-freight figure.
 */
final readonly class Dimensions
{
    public function __construct(
        public int $lengthMm,
        public int $widthMm,
        public int $heightMm,
    ) {
        if ($lengthMm < 0 || $widthMm < 0 || $heightMm < 0) {
            throw new \InvalidArgumentException('Dimensions cannot be negative.');
        }
    }

    public static function fromCentimeters(float $length, float $width, float $height): self
    {
        return new self(
            (int) round($length * 10),
            (int) round($width * 10),
            (int) round($height * 10),
        );
    }

    /**
     * Volumetric weight in grams. divisor is in cm³ per kg (carrier-specific).
     */
    public function volumetricWeightGrams(int $divisorCubicCmPerKg = 5000): int
    {
        $cubicCm = ($this->lengthMm / 10) * ($this->widthMm / 10) * ($this->heightMm / 10);

        return (int) round($cubicCm / $divisorCubicCmPerKg * 1000);
    }
}
