<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Contracts\EventDispatcher\Event;

class MachineStateChangeEvent extends Event implements JobEventInterface
{
    public function __construct(
        public readonly Machine $previous,
        public readonly Machine $current,
    ) {
    }

    public function getJobId(): string
    {
        return $this->previous->id;
    }
}
