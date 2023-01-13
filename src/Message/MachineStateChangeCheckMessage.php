<?php

declare(strict_types=1);

namespace App\Message;

class MachineStateChangeCheckMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $machineId
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $machineId,
        public readonly ?string $currentState,
    ) {
    }

    public function withCurrentState(string $state): MachineStateChangeCheckMessage
    {
        return new MachineStateChangeCheckMessage($this->authenticationToken, $this->machineId, $state);
    }
}
