<?php

declare(strict_types=1);

namespace App\Message;

class MachineStateChangeCheckMessage
{
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $machineId,
        public readonly string $currentState,
    ) {
    }
}
