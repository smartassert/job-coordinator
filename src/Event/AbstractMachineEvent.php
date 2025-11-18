<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractMachineEvent extends Event implements MachineEventInterface
{
    public function __construct(
        private readonly Machine $machine,
    ) {}

    public function getMachine(): Machine
    {
        return $this->machine;
    }
}
