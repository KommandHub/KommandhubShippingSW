<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs ShippingSyncTask.
 *
 * Handlers run in a worker, detached from any HTTP request:
 * - There is no sales channel context — resolve it explicitly per record.
 * - An uncaught throw makes the message retry and can wedge the queue. Catch,
 *   log with enough context to diagnose, and let the run finish.
 * - Keep the work batched and bounded; a handler that walks an unbounded result
 *   set will eventually exceed the worker's memory or time limit.
 */
#[AsMessageHandler(handles: ShippingSyncTask::class)]
class ShippingSyncTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly LoggerInterface $pluginLogger,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        try {
            // TODO: the actual synchronisation.
            $this->pluginLogger->info('Shipping sync task ran');
        } catch (\Throwable $exception) {
            $this->pluginLogger->error('Shipping sync task failed', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
