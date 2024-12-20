<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Enum\PreparationState;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Services\PreparationStateFactory;

readonly class GetResultsJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
        private PreparationStateFactory $preparationStateFactory,
        private MachineRepository $machineRepository,
    ) {
    }

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        $resultsJob = $this->resultsJobRepository->find($jobId);
        if (null === $resultsJob) {
            return MessageHandlingReadiness::NEVER;
        }

        if ($resultsJob->hasEndState()) {
            return MessageHandlingReadiness::NEVER;
        }

        $preparationState = $this->preparationStateFactory->createState($jobId);
        if (PreparationState::FAILED === $preparationState) {
            return MessageHandlingReadiness::NEVER;
        }

        $machine = $this->machineRepository->find($jobId);
        if (null === $machine) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
