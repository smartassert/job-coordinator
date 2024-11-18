<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;

class MachineHasActionFailureEvent extends AbstractMachineEvent implements JobEventInterface
{
    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        private readonly string $jobId,
        Machine $machine,
    ) {
        parent::__construct($machine);
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
