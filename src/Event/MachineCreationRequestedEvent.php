<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\MachineInterface;
use Symfony\Contracts\EventDispatcher\Event;

class MachineCreationRequestedEvent extends Event
{
    /**
     * @param non-empty-string $authenticationToken
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly MachineInterface $machine,
    ) {
    }
}
