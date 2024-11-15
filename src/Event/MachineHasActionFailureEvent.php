<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Contracts\EventDispatcher\Event;

class MachineHasActionFailureEvent extends Event implements JobEventInterface, MachineEventInterface
{
    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        private readonly string $jobId,
        private readonly Machine $machine,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getMachine(): Machine
    {
        return $this->machine;
    }
}
