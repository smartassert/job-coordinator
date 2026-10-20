<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\Machine as MachineEntity;
use App\Entity\MachineActionFailure;
use App\Model\MetaState;
use App\Model\RemoteRequestCollection;
use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-import-type SerializedMetaState from MetaState
 * @phpstan-import-type SerializedRemoteRequestCollectionGroup from RemoteRequestCollection
 * @phpstan-import-type SerializedPreparation from Preparation
 *
 * @phpstan-type SerializedMachine array{
 *   state_category: ?non-empty-string,
 *   ip_address: ?non-empty-string,
 *   action_failure: ?MachineActionFailure,
 *   meta_state: SerializedMetaState,
 *   requests: SerializedRemoteRequestCollectionGroup,
 *   preparation: SerializedPreparation,
 *   is_active: bool,
 * }
 */
readonly class Machine implements SerializeToArrayInterface
{
    public function __construct(
        private ?MachineEntity $entity,
        private RemoteRequestCollection $requests,
        private Preparation $preparation,
    ) {}

    public function getMetaState(): MetaState
    {
        if ($this->preparation->hasFailure()) {
            return new MetaState(true, false, false);
        }

        if (null === $this->entity) {
            return new MetaState(false, false, true);
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
            'meta_state' => $this->getMetaState()->jsonSerialize(),
            'requests' => $this->requests->jsonSerialize(),
            'preparation' => $this->preparation->jsonSerialize(),
            'is_active' => $this->entity?->getIsActive() ?? false,
        ];
    }
}
