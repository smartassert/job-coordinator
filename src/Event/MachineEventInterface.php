<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\Machine\MachineInterface;

interface MachineEventInterface
{
    public function getMachine(): MachineInterface;
}
