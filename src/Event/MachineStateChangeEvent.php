<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;

class MachineStateChangeEvent extends AbstractMachineEvent implements JobEventInterface
{
    public function __construct(
        public readonly Machine $previous,
        Machine $machine,
    ) {
        parent::__construct($machine);
    }

    public function getJobId(): string
    {
        return $this->previous->id;
    }
}
