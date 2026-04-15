<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\Machine as MachineEntity;
use App\Entity\MachineActionFailure;
use App\Enum\JobComponentName;
use App\Model\MetaState;
use App\Model\RemoteRequestCollection;
use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-type SerializedMachine array{
 *   state_category: ?non-empty-string,
 *   ip_address: ?non-empty-string,
 *   action_failure: ?MachineActionFailure,
 *   meta_state: MetaState,
 *   requests: RemoteRequestCollection,
 *   preparation: Preparation
 * }
 */
readonly class Machine implements SerializeToArrayInterface, JobComponentInterface
{
    public function __construct(
        private ?MachineEntity $entity,
        private RemoteRequestCollection $requests,
        private Preparation $preparation,
    ) {}

    public function getName(): JobComponentName
    {
        return JobComponentName::MACHINE;
    }

    public function getMetaState(): MetaState
    {
        if ($this->preparation->hasFailure()) {
            return new MetaState(true, false);
        }

        if (null === $this->entity) {
            return new MetaState(false, false);
        }

        return $this->entity->getMetaState();
    }

    /**
     * @return SerializedMachine
     */
    public function jsonSerialize(): array
    {
        $stateCategory = $this->entity?->getStateCategory() ?? null;
        if ($this->preparation->hasFailure()) {
            $stateCategory = 'end';
        }

        return [
            'state_category' => $stateCategory,
            'ip_address' => $this->entity?->getIp() ?? null,
            'action_failure' => $this->entity?->getActionFailure() ?? null,
            'meta_state' => $this->getMetaState(),
            'requests' => $this->requests,
            'preparation' => $this->preparation,
        ];
    }
}
