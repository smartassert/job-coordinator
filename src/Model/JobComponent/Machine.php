<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Model\Machine\ActionFailureInterface;
use App\Model\Machine\Machine as MachineModel;
use App\Model\MetaState;
use App\Model\RemoteRequestCollection;
use App\Model\SerializeToArrayInterface;

/**
 * @phpstan-import-type SerializedMetaState from MetaState
 * @phpstan-import-type SerializedRemoteRequestCollectionGroup from RemoteRequestCollection
 * @phpstan-import-type SerializedPreparation from Preparation
 * @phpstan-import-type SerializedMachineActionFailure from ActionFailureInterface
 *
 * @phpstan-type SerializedMachine array{
 *   state_category: ?string,
 *   ip_address: ?string,
 *   action_failure: ?SerializedMachineActionFailure,
 *   meta_state: SerializedMetaState,
 *   requests: SerializedRemoteRequestCollectionGroup,
 *   preparation: SerializedPreparation,
 *   is_active: bool,
 * }
 */
readonly class Machine implements SerializeToArrayInterface
{
    public function __construct(
        private ?MachineModel $model,
        private RemoteRequestCollection $requests,
        private Preparation $preparation,
    ) {}

    public function getMetaState(): MetaState
    {
        if ($this->preparation->hasFailure()) {
            return new MetaState(true, false, false);
        }

        if (null === $this->model) {
            return new MetaState(false, false, true);
        }

        return $this->model->getMetaState();
    }

    /**
     * @return SerializedMachine
     */
    public function toArray(): array
    {
        $stateCategory = $this->model?->getStateCategory();
        if ($this->preparation->hasFailure()) {
            $stateCategory = 'end';
        }

        return [
            'state_category' => $stateCategory,
            'ip_address' => $this->model?->getIpAddress(),
            'action_failure' => $this->model?->getActionFailure()?->toArray(),
            'meta_state' => $this->getMetaState()->toArray(),
            'requests' => $this->requests->toArray(),
            'preparation' => $this->preparation->toArray(),
            'is_active' => $this->model?->isActive() ?? false,
        ];
    }
}
