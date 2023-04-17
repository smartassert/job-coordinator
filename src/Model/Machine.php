<?php

declare(strict_types=1);

namespace App\Model;

use SmartAssert\WorkerManagerClient\Model\Machine as WorkerManagerMachine;

class Machine implements \JsonSerializable
{
    public function __construct(
        private readonly WorkerManagerMachine $workerManagerMachine
    ) {
    }

    /**
     * @return array{
     *   id: non-empty-string,
     *   state: string,
     *   state_category: string,
     *   ip_addresses: non-empty-string[]
     *  }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->workerManagerMachine->id,
            'state' => $this->workerManagerMachine->state,
            'state_category' => $this->workerManagerMachine->stateCategory,
            'ip_addresses' => $this->workerManagerMachine->ipAddresses,
        ];
    }
}