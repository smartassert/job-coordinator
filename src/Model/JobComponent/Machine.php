<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\Machine as MachineEntity;
use App\Entity\MachineActionFailure;
use App\Enum\JobComponentName;
use App\Model\MetaState;
use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-type SerializedMachine array{
 *   state_category: ?non-empty-string,
 *   ip_address: ?non-empty-string,
 *   action_failure: ?MachineActionFailure,
 *   meta_state: MetaState
 * }
 */
readonly class Machine implements SerializeToArrayInterface, NamedJobComponentInterface
{
    public function __construct(
        private MachineEntity $entity,
    ) {}

    public function getName(): JobComponentName
    {
        return JobComponentName::MACHINE;
    }

    /**
     * @return SerializedMachine
     */
    public function jsonSerialize(): array
    {
        return [
            'state_category' => $this->entity->getStateCategory(),
            'ip_address' => $this->entity->getIp(),
            'action_failure' => $this->entity->getActionFailure(),
            'meta_state' => $this->entity->getMetaState(),
        ];
    }
}
