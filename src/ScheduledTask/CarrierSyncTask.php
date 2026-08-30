<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Recurring prefetch of every provider's carrier catalogue into the store, so
 * the config UI never waits on a live listCarriers() call. Daily is plenty —
 * carrier line-ups change rarely; the admin "refresh" covers urgent updates.
 */
class CarrierSyncTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'kommandhub_shipping.carrier_sync';
    }

    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
