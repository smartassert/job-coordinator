<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;

class MachineIsActiveEvent extends AbstractMachineEvent implements JobEventInterface
{
    use GetJobIdTrait;

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $ipAddress
     */
    public function __construct(
        private readonly string $jobId,
        public readonly string $ipAddress,
        Machine $machine,
    ) {
        parent::__construct($machine);
    }
}
