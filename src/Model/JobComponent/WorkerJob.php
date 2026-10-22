<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\WorkerJobCreationFailure;
use App\Enum\WorkerComponentName;
use App\Model\FailedWorkerComponentState;
use App\Model\MetaState;
use App\Model\RemoteRequestCollection;
use App\Model\SerializeToArrayInterface;
use App\Model\WorkerComponentStateInterface;

/**
 * @phpstan-import-type SerializedMetaState from MetaState
 * @phpstan-import-type SerializedRemoteRequestCollectionGroup from RemoteRequestCollection
 * @phpstan-import-type SerializedPreparation from Preparation
 * @phpstan-import-type SerializedWorkerComponentState from WorkerComponentStateInterface
 * @phpstan-import-type SerializedWorkerJobCreationFailure from WorkerJobCreationFailure
 *
 * @phpstan-type SerializedWorkerState array{
 *   state: string,
 *   meta_state: SerializedMetaState,
 *   components: array{
 *     compilation: SerializedWorkerComponentState,
 *     execution: SerializedWorkerComponentState,
 *     event_delivery: SerializedWorkerComponentState
 *   },
 *   preparation: SerializedPreparation,
 *   requests: SerializedRemoteRequestCollectionGroup,
 *   creation_failure?: SerializedWorkerJobCreationFailure,
 * }
 */
class WorkerJob implements SerializeToArrayInterface
{
    public function __construct(
        private readonly WorkerComponentStateInterface $applicationState,
        private readonly WorkerComponentStateInterface $compilationState,
        private readonly WorkerComponentStateInterface $executionState,
        private readonly WorkerComponentStateInterface $eventDeliveryState,
        private readonly ?WorkerJobCreationFailure $failure,
        private readonly RemoteRequestCollection $requests,
        private readonly Preparation $preparation,
    ) {}

    /**
     * @return SerializedWorkerState
     */
    public function toArray(): array
    {
        $data = [
            'state' => $this->getState(),
            'meta_state' => $this->getMetaState()->toArray(),
            'components' => [
                WorkerComponentName::COMPILATION->value => $this->compilationState->toArray(),
                WorkerComponentName::EXECUTION->value => $this->executionState->toArray(),
                WorkerComponentName::EVENT_DELIVERY->value => $this->eventDeliveryState->toArray(),
            ],
            'preparation' => $this->preparation->toArray(),
            'requests' => $this->requests->toArray(),
        ];

        if (null !== $this->failure) {
            $data['creation_failure'] = $this->failure->toArray();
        }

        return $data;
    }

    public function getMetaState(): MetaState
    {
        if ($this->hasFailed()) {
            return new MetaState(true, false, false);
        }

        return $this->applicationState->getMetaState();
    }

    private function hasFailed(): bool
    {
        return $this->preparation->hasFailure() || $this->failure instanceof WorkerJobCreationFailure;
    }

    private function getState(): string
    {
        $applicationState = $this->hasFailed()
            ? new FailedWorkerComponentState()
            : $this->applicationState;

        return $applicationState->getState();
    }
}
