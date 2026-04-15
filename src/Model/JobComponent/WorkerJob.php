<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\WorkerJobCreationFailure;
use App\Enum\JobComponentName;
use App\Enum\WorkerComponentName;
use App\Model\FailedWorkerComponentState;
use App\Model\MetaState;
use App\Model\RemoteRequestCollection;
use App\Model\SerializeToArrayInterface;
use App\Model\WorkerComponentStateInterface;

/**
 * @phpstan-type SerializedWorkerState array{
 *   state: string,
 *   meta_state: MetaState,
 *   components: array{
 *     compilation: WorkerComponentStateInterface,
 *     execution: WorkerComponentStateInterface,
 *     event_delivery: WorkerComponentStateInterface
 *   },
 *   failure?: WorkerJobCreationFailure,
 *   preparation: Preparation,
 *   requests: RemoteRequestCollection
 * }
 */
class WorkerJob implements SerializeToArrayInterface, JobComponentInterface
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

    public function getName(): JobComponentName
    {
        return JobComponentName::WORKER_JOB;
    }

    /**
     * @return SerializedWorkerState
     */
    public function jsonSerialize(): array
    {
        $data = [
            'state' => $this->getState(),
            'meta_state' => $this->getMetaState(),
            'components' => [
                WorkerComponentName::COMPILATION->value => $this->compilationState,
                WorkerComponentName::EXECUTION->value => $this->executionState,
                WorkerComponentName::EVENT_DELIVERY->value => $this->eventDeliveryState,
            ],
            'preparation' => $this->preparation,
            'requests' => $this->requests,
        ];

        if (null !== $this->failure) {
            $data['creation_failure'] = $this->failure;
        }

        return $data;
    }

    public function getMetaState(): MetaState
    {
        if ($this->hasFailed()) {
            return new MetaState(true, false);
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
