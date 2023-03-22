<?php

declare(strict_types=1);

namespace App\Message;

use SmartAssert\WorkerManagerClient\Model\Machine;

class MachineStateChangeCheckMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $machineId
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $machineId,
        public readonly string $currentState,
    ) {
    }

    /**
     * @param non-empty-string $authenticationToken
     */
    public static function createFromMachine(
        string $authenticationToken,
        Machine $machine
    ): MachineStateChangeCheckMessage {
        return new MachineStateChangeCheckMessage($authenticationToken, $machine->id, $machine->state);
    }

    public function withCurrentState(string $state): MachineStateChangeCheckMessage
    {
        return new MachineStateChangeCheckMessage($this->authenticationToken, $this->machineId, $state);
    }
}
