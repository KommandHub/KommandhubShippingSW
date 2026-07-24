<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Stamps every record on the plugin's channel with the current correlation id,
 * so the whole life of one shipment operation is greppable across log lines.
 * Registered against the plugin's monolog channel in services.yml.
 */
final class CorrelationIdProcessor implements ProcessorInterface
{
    public function __construct(private readonly CorrelationId $correlationId)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: [...$record->extra, 'correlation_id' => $this->correlationId->get()]);
    }
}
