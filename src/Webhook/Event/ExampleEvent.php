<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Webhook\Event;

/**
 * Example webhook event — replace with the provider's real event types.
 */
class ExampleEvent extends WebhookEvent
{
    public static function getEventName(): string
    {
        return 'example.completed';
    }
}
