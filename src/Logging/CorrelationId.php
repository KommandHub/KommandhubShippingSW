<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Logging;

/**
 * Holds one correlation id for the current request/worker cycle. Registered as
 * a shared service, so every log line, outbound API call and async message in
 * the same cycle can be tied together. A webhook or messenger consumer sets the
 * inbound id via set(); otherwise one is lazily generated.
 */
final class CorrelationId
{
    private ?string $id = null;

    public function get(): string
    {
        return $this->id ??= bin2hex(random_bytes(16));
    }

    public function set(string $id): void
    {
        $this->id = $id;
    }
}
