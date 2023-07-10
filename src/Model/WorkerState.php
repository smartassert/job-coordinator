<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;

/**
 * @phpstan-import-type SerializedWorkerComponentState from WorkerComponentState
 *
 * @phpstan-type SerializedWorkerState array{
 *   'application': SerializedWorkerComponentState,
 *   'compilation': SerializedWorkerComponentState,
 *   'execution': SerializedWorkerComponentState,
 *   'event_delivery': SerializedWorkerComponentState,
 * }
 */
class WorkerState
{
    public function __construct(
        private readonly WorkerComponentState $applicationState,
        private readonly WorkerComponentState $compilationState,
        private readonly WorkerComponentState $executionState,
        private readonly WorkerComponentState $eventDeliveryState,
    ) {
    }

    /**
     * @return SerializedWorkerState
     */
    public function toArray(): array
    {
        return [
            WorkerComponentName::APPLICATION->value => $this->applicationState->toArray(),
            WorkerComponentName::COMPILATION->value => $this->compilationState->toArray(),
            WorkerComponentName::EXECUTION->value => $this->executionState->toArray(),
            WorkerComponentName::EVENT_DELIVERY->value => $this->eventDeliveryState->toArray(),
        ];
    }
}
