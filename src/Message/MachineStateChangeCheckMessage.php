<?php

declare(strict_types=1);

namespace App\Message;

class MachineStateChangeCheckMessage
{
    /**
     * @param non-empty-string      $authenticationToken
     * @param non-empty-string      $machineId
     * @param null|non-empty-string $currentState
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $machineId,
        public readonly ?string $currentState,
    ) {
    }

    /**
     * @param non-empty-string $state
     */
    public function withCurrentState(string $state): MachineStateChangeCheckMessage
    {
        return new MachineStateChangeCheckMessage($this->authenticationToken, $this->machineId, $state);
    }
}
