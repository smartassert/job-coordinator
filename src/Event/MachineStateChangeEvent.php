<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Contracts\EventDispatcher\Event;

class MachineStateChangeEvent extends Event implements JobEventInterface, MachineEventInterface
{
    public function __construct(
        public readonly Machine $previous,
        private readonly Machine $machine,
    ) {
    }

    public function getJobId(): string
    {
        return $this->previous->id;
    }

    public function getMachine(): Machine
    {
        return $this->machine;
    }
}
