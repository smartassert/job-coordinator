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
        return new PreparationState($this->createState($job->getId()), $this->requestStatesFactory->create($job));
    }

    public function createState(string $jobId): PreparationStateEnum
    {
        $componentPreparationStates = $this->componentPreparationFactory->getAll($jobId);

        return $this->preparationStateReducer->reduce($componentPreparationStates);
    }
}
