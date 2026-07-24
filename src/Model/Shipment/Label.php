<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Shipment;

/**
 * A printable waybill. A provider returns either an inline document (base64) or
 * a URL to fetch one; at least one must be present, which the constructor
 * enforces so downstream code never has to handle a label that is neither.
 */
final readonly class Label
{
    public function __construct(
        public LabelFormat $format,
        public ?string $url = null,
        public ?string $base64 = null,
    ) {
        if (null === $url && null === $base64) {
            throw new \InvalidArgumentException('A Label must carry either a url or base64 contents.');
        }
    }

    public function hasContents(): bool
    {
        return null !== $this->base64;
    }
}
