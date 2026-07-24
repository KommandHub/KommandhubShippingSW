<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Model\Tracking;

/**
 * Canonical parcel lifecycle status. Each provider maps its own vocabulary of
 * status codes onto these; the core (and the Shopware delivery state machine)
 * only ever sees these values.
 */
enum TrackingStatus: string
{
    case PENDING = 'pending';
    case CREATED = 'created';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case EXCEPTION = 'exception';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    case UNKNOWN = 'unknown';
}
