<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;

interface MachineEventInterface
{
    public function getMachine(): Machine;
}
