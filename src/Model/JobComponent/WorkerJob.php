<?php

declare(strict_types=1);

namespace App\Model\JobComponent;

use App\Entity\WorkerJobCreationFailure;
use App\Enum\JobComponentName;
use App\Enum\WorkerComponentName;
use App\Model\FailedWorkerComponentState;
use App\Model\MetaState;
use App\Model\SerializeToArrayInterface;
use App\Model\WorkerComponentStateInterface;

/**
 * @phpstan-import-type SerializedWorkerComponentState from WorkerComponentStateInterface
 *
 * @phpstan-type SerializedWorkerState array{
 *   state: ?non-empty-string,
 *   meta_state: array{
 *     ended: bool,
 *     succeeded: bool
 *   },
 *   components: array{
 *     compilation: SerializedWorkerComponentState,
 *     execution: SerializedWorkerComponentState,
 *     event_delivery: SerializedWorkerComponentState
 *   },
 *   failure?: WorkerJobCreationFailure
 * }
 */
class WorkerJob implements SerializeToArrayInterface, NamedJobComponentInterface
{
    public function __construct(
        private readonly WorkerComponentStateInterface $applicationState,
        private readonly WorkerComponentStateInterface $compilationState,
        private readonly WorkerComponentStateInterface $executionState,
        private readonly WorkerComponentStateInterface $eventDeliveryState,
        private readonly ?WorkerJobCreationFailure $failure,
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
        $applicationState = $this->failure instanceof WorkerJobCreationFailure
            ? new FailedWorkerComponentState()
            : $this->applicationState;

        $data = $applicationState->toArray();
        $data['components'] = [
            WorkerComponentName::COMPILATION->value => $this->compilationState->toArray(),
            WorkerComponentName::EXECUTION->value => $this->executionState->toArray(),
            WorkerComponentName::EVENT_DELIVERY->value => $this->eventDeliveryState->toArray(),
        ];

        if (null !== $this->failure) {
            $data['creation_failure'] = $this->failure->jsonSerialize();
        }

        return $data;
    }

    public function getMetaState(): MetaState
    {
        return $this->applicationState->getMetaState();
    }
}
