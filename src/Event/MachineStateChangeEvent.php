<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Contracts\EventDispatcher\Event;

class MachineStateChangeEvent extends Event
{
    public function __construct(
        public readonly Machine $machine,
        public readonly ?string $previous,
    ) {
    }
}
