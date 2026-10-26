<?php

declare(strict_types=1);

namespace App\Event;

class MachineTerminationRequestedEvent extends AbstractMachineEvent implements JobEventInterface
{
    public function getJobId(): string
    {
        return $this->getMachine()->getId();
    }
}
