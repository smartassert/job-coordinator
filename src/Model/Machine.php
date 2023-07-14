<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @phpstan-type SerializedMachine array{
 *   state_category: ?non-empty-string,
 *   ip_address: ?non-empty-string
 * }
 */
class Machine
{
    public function __construct(
        private readonly ?MachineInterface $machine,
    ) {
    }

    /**
     * @return SerializedMachine
     */
    public function toArray(): array
    {
        return [
            'state_category' => $this->machine?->getStateCategory() ?? null,
            'ip_address' => $this->machine?->getIp() ?? null,
        ];
    }
}
