<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\ResultsJobRepository;

class CreateResultsJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private readonly ResultsJobRepository $resultsJobRepository,
    ) {
    }

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        if ($this->resultsJobRepository->has($jobId)) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
