<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\Machine\MachineInterface;

class MachineTerminationRequestedEvent extends AbstractMachineEvent implements JobEventInterface
{
    public function __construct(
        MachineInterface $machine,
    ) {
        parent::__construct($machine);
    }

    public function getJobId(): string
    {
        return $this->getMachine()->getId();
    }
}
