<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Pickup;

/**
 * A confirmed courier collection. status stays a free string: pickup lifecycles
 * vary too much between carriers to force onto a shared enum, and the core only
 * displays it — it does not branch on it.
 */
final readonly class Pickup
{
    public function __construct(
        public string $providerKey,
        public string $providerPickupId,
        public \DateTimeImmutable $scheduledAt,
        public string $status,
    ) {
    }
}
