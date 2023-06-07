<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerClient\Model\Job as WorkerJob;
use Symfony\Contracts\EventDispatcher\Event;

class WorkerJobStartRequestedEvent extends Event
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $jobId,
        public readonly WorkerJob $workerJob,
    ) {
    }
}
