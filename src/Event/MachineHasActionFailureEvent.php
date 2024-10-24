<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\ActionFailure;
use Symfony\Contracts\EventDispatcher\Event;

class MachineHasActionFailureEvent extends Event implements JobEventInterface
{
    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        private readonly string $jobId,
        public readonly ActionFailure $machineActionFailure,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
