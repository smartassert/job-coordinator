<?php

declare(strict_types=1);

namespace App\Event;

use App\Model\Machine\MachineInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractMachineEvent extends Event implements MachineEventInterface
{
    public function __construct(
        private readonly MachineInterface $machine,
    ) {}

    public function getMachine(): MachineInterface
    {
        return $this->machine;
    }
}
