<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\WorkerComponentName;

/**
 * @phpstan-import-type SerializedWorkerComponentState from WorkerComponentStateInterface
 *
 * @phpstan-type SerializedWorkerState array{
 *   state: non-empty-string,
 *   is_end_state: bool,
 *   components: array{
 *     compilation: SerializedWorkerComponentState,
 *     execution: SerializedWorkerComponentState,
 *     event_delivery: SerializedWorkerComponentState
 *   }
 * }
 */
class WorkerState
{
    public function __construct(
        private readonly WorkerComponentStateInterface $applicationState,
        private readonly WorkerComponentStateInterface $compilationState,
        private readonly WorkerComponentStateInterface $executionState,
        private readonly WorkerComponentStateInterface $eventDeliveryState,
    ) {
    }

    /**
     * @return SerializedWorkerState
     */
    public function toArray(): array
    {
        return array_merge(
            $this->applicationState->toArray(),
            [
                'components' => [
                    WorkerComponentName::COMPILATION->value => $this->compilationState->toArray(),
                    WorkerComponentName::EXECUTION->value => $this->executionState->toArray(),
                    WorkerComponentName::EVENT_DELIVERY->value => $this->eventDeliveryState->toArray(),
                ],
            ]
        );
    }
}
