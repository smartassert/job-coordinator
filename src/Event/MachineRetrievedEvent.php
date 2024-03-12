<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\MachineInterface;
use Symfony\Contracts\EventDispatcher\Event;

class MachineRetrievedEvent extends Event
{
    /**
     * @param non-empty-string $authenticationToken
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly MachineInterface $previous,
        public readonly MachineInterface $current,
    ) {
    }
}
