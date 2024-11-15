<?php

declare(strict_types=1);

namespace App\Event;

use App\Event\AuthenticatingEventInterface as AuthenticatingEvent;
use App\Event\MachineEventInterface as MachineEvent;
use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Contracts\EventDispatcher\Event;

class MachineIsActiveEvent extends Event implements JobEventInterface, AuthenticatingEvent, MachineEvent
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $ipAddress
     */
    public function __construct(
        private readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly string $ipAddress,
        private readonly Machine $machine,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getAuthenticationToken(): string
    {
        return $this->authenticationToken;
    }

    public function getMachine(): Machine
    {
        return $this->machine;
    }
}
