<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Machine as MachineEntity;

/**
 * @phpstan-import-type SerializedRemoteRequest from SerializableRemoteRequestInterface
 *
 * @phpstan-type SerializedMachine array{
 *   request: SerializedRemoteRequest,
 *   state_category: ?non-empty-string,
 *   ip_address: ?non-empty-string
 * }
 */
class Machine
{
    public function __construct(
        private readonly MachineEntity $entity,
        private readonly SerializableRemoteRequestInterface $request,
    ) {
    }

    /**
     * @return SerializedMachine
     */
    public function toArray(): array
    {
        return [
            'request' => $this->request->toArray(),
            'state_category' => $this->entity->getStateCategory(),
            'ip_address' => $this->entity->getIp(),
        ];
    }
}
