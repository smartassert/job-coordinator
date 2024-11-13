<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequestFailure;
use App\Enum\JobComponent;
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RequestState;
use App\Model\JobInterface;

/**
 * @phpstan-type SerializedPreparationState array{
 *   state: PreparationStateEnum,
 *   request_states: array<RequestState>,
 *   failures: array<value-of<JobComponent>, RemoteRequestFailure|null>
 * }
 */
class PreparationStateFactory
{
    public function __construct(
        private readonly ComponentPreparationFactory $componentPreparationFactory,
        private readonly PreparationStateReducer $preparationStateReducer,
        private readonly RequestStatesFactory $requestStatesFactory,
    ) {
    }

    /**
     * @return SerializedPreparationState
     */
    public function create(JobInterface $job): array
    {
        $componentPreparationStates = $this->componentPreparationFactory->getAll($job);

        $componentFailures = [];
        foreach ($componentPreparationStates as $name => $preparationState) {
            if (PreparationStateEnum::FAILED === $preparationState->state) {
                $componentFailures[$name] = $preparationState->failure;
            }
        }

        return [
            'state' => $this->preparationStateReducer->reduce($componentPreparationStates),
            'request_states' => $this->requestStatesFactory->create($job),
            'failures' => $componentFailures,
        ];
    }
}
