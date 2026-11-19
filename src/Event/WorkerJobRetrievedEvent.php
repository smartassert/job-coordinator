<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerClient\Model\ApplicationState;

class WorkerJobRetrievedEvent extends AbstractWorkerEvent
{
    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        string $jobId,
        public readonly ApplicationState $state,
    ) {
        parent::__construct($jobId);
    }
}
