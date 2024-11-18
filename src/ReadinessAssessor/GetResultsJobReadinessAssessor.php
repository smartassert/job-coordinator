<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\ResultsJobRepository;
use App\Services\JobPreparationInspectorInterface;

readonly class GetResultsJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
        private JobPreparationInspectorInterface $jobPreparationInspector,
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

        if ($this->jobPreparationInspector->hasFailed($jobId)) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
