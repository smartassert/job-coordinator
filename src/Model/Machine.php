<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Machine as MachineEntity;

/**
 * @phpstan-type SerializedMachine array{state_category: ?non-empty-string, ip_address: ?non-empty-string}
 */
class Machine
{
    public function __construct(
        private readonly MachineEntity $entity,
    ) {
    }

    /**
     * @return SerializedMachine
     */
    public function toArray(): array
    {
        return [
            'state_category' => $this->entity->getStateCategory(),
            'ip_address' => $this->entity->getIp(),
        ];
    }
}
