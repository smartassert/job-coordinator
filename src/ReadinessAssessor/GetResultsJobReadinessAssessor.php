<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Enum\PreparationState;
use App\Repository\ResultsJobRepository;
use App\Services\PreparationStateFactory;

readonly class GetResultsJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
        private PreparationStateFactory $preparationStateFactory,
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

        return MessageHandlingReadiness::NOW;
    }
}
