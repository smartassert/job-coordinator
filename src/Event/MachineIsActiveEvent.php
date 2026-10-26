<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\Machine\Machine;

class MachineIsActiveEvent extends AbstractMachineEvent implements JobEventInterface
{
    /**
     * @param non-empty-string $ipAddress
     */
    public function __construct(
        public readonly string $ipAddress,
        Machine $machine,
    ) {
        parent::__construct($machine);
    }

    public function getJobId(): string
    {
        return $this->getMachine()->getId();
    }
}
