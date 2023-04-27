<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;

class MachineIsActiveEvent extends MachineStateChangeEvent
{
    public function __construct(
        string $authenticationToken,
        Machine $previous,
        Machine $current,
        public readonly string $ipAddress,
    ) {
        parent::__construct($authenticationToken, $previous, $current);
    }
}
