<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\ValueObject;

/**
 * A parcel weight, canonicalised to grams. Providers quote in kg or g; the
 * adapter converts on the way in so the core only ever compares grams.
 */
final readonly class Weight
{
    public function __construct(public int $grams)
    {
        if ($grams < 0) {
            throw new \InvalidArgumentException('Weight cannot be negative.');
        }
    }

    public static function fromGrams(int $grams): self
    {
        return new self($grams);
    }

    public static function fromKilograms(float $kilograms): self
    {
        return new self((int) round($kilograms * 1000));
    }

    public function kilograms(): float
    {
        return $this->grams / 1000;
    }
}
