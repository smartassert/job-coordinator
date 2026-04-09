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
    ) {}

    public function create(JobInterface $job): PreparationState
    {
        return new PreparationState($this->createState($job->getId()));
    }

    public function createState(string $jobId): PreparationStateEnum
    {
        $componentPreparationStates = $this->componentPreparationFactory->getAll($jobId);

        return $this->preparationStateReducer->reduce($componentPreparationStates);
    }
}
