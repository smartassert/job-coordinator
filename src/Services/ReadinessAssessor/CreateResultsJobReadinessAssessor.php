<?php

declare(strict_types=1);

namespace App\Services\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\ResultsJobRepository;

class CreateResultsJobReadinessAssessor
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
