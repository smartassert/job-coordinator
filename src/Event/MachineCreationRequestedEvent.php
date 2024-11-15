<?php

declare(strict_types=1);

namespace App\Event;

use App\Event\AuthenticatingEventInterface as AuthenticatingEvent;
use SmartAssert\WorkerManagerClient\Model\Machine;

class MachineCreationRequestedEvent extends AbstractMachineEvent implements JobEventInterface, AuthenticatingEvent
{
    /**
     * @param non-empty-string $authenticationToken
     */
    public function __construct(
        private readonly string $authenticationToken,
        Machine $machine,
    ) {
        parent::__construct($machine);
    }

    public function getJobId(): string
    {
        return $this->getMachine()->id;
    }

    public function getAuthenticationToken(): string
    {
        return $this->authenticationToken;
    }
}
