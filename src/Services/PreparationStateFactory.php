<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\PreparationState as PreparationStateEnum;
use App\Model\JobInterface;
use App\Model\PreparationState;

class PreparationStateFactory
{
    public function __construct(
        private readonly ComponentPreparationFactory $componentPreparationFactory,
        private readonly PreparationStateReducer $preparationStateReducer,
        private readonly RequestStatesFactory $requestStatesFactory,
    ) {}

    public function create(JobInterface $job): PreparationState
    {
        $componentPreparationStates = $this->componentPreparationFactory->getAll($job->getId());

        $componentFailures = [];
        foreach ($componentPreparationStates as $name => $preparationState) {
            if (PreparationStateEnum::FAILED === $preparationState->state && null !== $preparationState->failure) {
                $componentFailures[$name] = $preparationState->failure;
            }
        }

        return new PreparationState(
            $this->createState($job->getId()),
            $this->requestStatesFactory->create($job),
            $componentFailures,
        );
    }

    public function createState(string $jobId): PreparationStateEnum
    {
        $componentPreparationStates = $this->componentPreparationFactory->getAll($jobId);

        return $this->preparationStateReducer->reduce($componentPreparationStates);
    }
}
