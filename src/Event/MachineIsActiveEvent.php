<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\Machine\MachineInterface;

class MachineIsActiveEvent extends AbstractMachineEvent implements JobEventInterface
{
    /**
     * @param non-empty-string $ipAddress
     */
    public function __construct(
        public readonly string $ipAddress,
        MachineInterface $machine,
    ) {
        parent::__construct($machine);
    }

    public function getJobId(): string
    {
        return $this->getMachine()->getId();
    }
}
