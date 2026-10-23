<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\Machine\MachineInterface;

class MachineStateChangeEvent extends AbstractMachineEvent implements JobEventInterface
{
    public function __construct(
        public readonly MachineInterface $previous,
        MachineInterface $machine,
    ) {
        parent::__construct($machine);
    }

    public function getJobId(): string
    {
        return $this->getMachine()->getId();
    }
}
