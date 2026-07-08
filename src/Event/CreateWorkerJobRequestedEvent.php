<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerClient\Model\Job as WorkerJob;

class CreateWorkerJobRequestedEvent extends AbstractWorkerEvent
{
    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $machineIpAddress
     */
    public function __construct(
        string $jobId,
        string $machineIpAddress,
        public readonly WorkerJob $workerJob,
    ) {
        parent::__construct($jobId, $machineIpAddress);
    }
}
