<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerClient\Model\Job as WorkerJob;

class CreateWorkerJobRequestedEvent extends AbstractWorkerEvent implements AuthenticatingEventInterface
{
    use GetAuthenticationTokenTrait;

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $machineIpAddress
     */
    public function __construct(
        private readonly string $authenticationToken,
        string $jobId,
        string $machineIpAddress,
        public readonly WorkerJob $workerJob,
    ) {
        parent::__construct($jobId, $machineIpAddress);
    }
}
