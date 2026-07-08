<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;

class MachineCreationRequestedEvent extends AbstractMachineEvent implements JobEventInterface
{
    public function __construct(
        Machine $machine,
    ) {
        parent::__construct($machine);
    }

    public function getJobId(): string
    {
        return $this->getMachine()->id;
    }
}
