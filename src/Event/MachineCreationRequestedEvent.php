<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerManagerClient\Model\Machine;
use Symfony\Contracts\EventDispatcher\Event;

class MachineCreationRequestedEvent extends Event implements JobEventInterface, AuthenticatingEventInterface
{
    /**
     * @param non-empty-string $authenticationToken
     */
    public function __construct(
        private readonly string $authenticationToken,
        public readonly Machine $machine,
    ) {
    }

    public function getJobId(): string
    {
        return $this->machine->id;
    }

    public function getAuthenticationToken(): string
    {
        return $this->authenticationToken;
    }
}
