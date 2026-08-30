<?php

declare(strict_types=1);

namespace Kommandhub\ShippingSW\ScheduledTask;

use Kommandhub\ShippingSW\Provider\Catalog\CarrierCatalogSynchronizer;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CarrierSyncTask::class)]
class CarrierSyncTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly CarrierCatalogSynchronizer $synchronizer,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->synchronizer->syncAll(Context::createDefaultContext());
    }
}
