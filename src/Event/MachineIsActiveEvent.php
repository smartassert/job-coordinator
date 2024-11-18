<?php

declare(strict_types=1);

namespace App\Event;

use App\Event\AuthenticatingEventInterface as AuthenticatingEvent;
use SmartAssert\WorkerManagerClient\Model\Machine;

class MachineIsActiveEvent extends AbstractMachineEvent implements JobEventInterface, AuthenticatingEvent
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
        Machine $machine,
    ) {
        parent::__construct($machine);
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getAuthenticationToken(): string
    {
        return $this->authenticationToken;
    }
}
