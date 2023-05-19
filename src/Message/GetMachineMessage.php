<?php

declare(strict_types=1);

namespace App\Message;

use SmartAssert\WorkerManagerClient\Model\Machine;

class GetMachineMessage
{
    /**
     * @param non-empty-string $authenticationToken
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly Machine $machine,
    ) {
    }
}