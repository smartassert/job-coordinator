<?php

declare(strict_types=1);

namespace App\Event;

use App\Event\AuthenticatingEventInterface as AuthenticatingEvent;
use App\Event\MachineEventInterface as MachineEvent;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Contracts\EventDispatcher\Event;

class MachineRetrievedEvent extends Event implements JobEventInterface, AuthenticatingEvent, MachineEvent
{
    /**
     * @param non-empty-string $authenticationToken
     */
    public function __construct(
        private readonly string $authenticationToken,
        public readonly Machine $previous,
        public readonly Machine $current,
    ) {
    }

    public function getJobId(): string
    {
        return $this->previous->id;
    }

    public function getAuthenticationToken(): string
    {
        return $this->authenticationToken;
    }

    public function getMachine(): Machine
    {
        return $this->current;
    }
}
