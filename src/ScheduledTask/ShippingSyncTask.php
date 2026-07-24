<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Recurring background job.
 *
 * The name must be globally unique across the installation — prefix it with the
 * plugin slug. Changing the interval takes effect after the next
 * `bin/console scheduled-task:register` / plugin update.
 */
class ShippingSyncTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'kommandhub_shipping.sync';
    }

    /**
     * Interval in seconds.
     */
    public static function getDefaultInterval(): int
    {
        return 3600;
    }
}
