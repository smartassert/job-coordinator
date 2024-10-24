<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Contracts\EventDispatcher\Event;

class MachineStateChangeEvent extends Event implements JobEventInterface
{
    /**
     * @param non-empty-string $authenticationToken
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly Machine $previous,
        public readonly Machine $current,
    ) {
    }

    public function getJobId(): string
    {
        return $this->previous->id;
    }
}
