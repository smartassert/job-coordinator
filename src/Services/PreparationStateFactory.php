<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\JobComponentName as ComponentName;
use App\Enum\PreparationState as PreparationStateEnum;
use App\Model\ComponentFailure;
use App\Model\ComponentFailures;
use App\Model\PreparationState;

class PreparationStateFactory
{
    public function __construct(
        private readonly ComponentPreparationFactory $componentPreparationFactory,
        private readonly PreparationStateReducer $preparationStateReducer,
    ) {
    }

    public function create(Job $job): PreparationState
    {
        $componentPreparationStates = [
            ComponentName::RESULTS_JOB->value => $this->componentPreparationFactory->getForResultsJob($job),
            ComponentName::SERIALIZED_SUITE->value => $this->componentPreparationFactory->getForSerializedSuite($job),
            ComponentName::MACHINE->value => $this->componentPreparationFactory->getForMachine($job),
            ComponentName::WORKER_JOB->value => $this->componentPreparationFactory->getForWorkerJob($job),
        ];

        $componentFailures = [];
        foreach ($componentPreparationStates as $name => $preparationState) {
            if (PreparationStateEnum::FAILED === $preparationState->state) {
                $componentFailures[$name] = new ComponentFailure($name, $preparationState->failure);
            }
        }

        return new PreparationState(
            $this->preparationStateReducer->reduce($componentPreparationStates),
            new ComponentFailures($componentFailures)
        );
    }
}
