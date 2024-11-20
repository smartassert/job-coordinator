<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\ResultsJobRepository;
use App\Services\SerializedSuiteStore;

readonly class CreateWorkerJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private SerializedSuiteStore $serializedSuiteStore,
        private ResultsJobRepository $resultsJobRepository,
    ) {
    }

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        $serializedSuite = $this->serializedSuiteStore->retrieve($jobId);
        $resultsJob = $this->resultsJobRepository->find($jobId);
        if (null === $serializedSuite || $serializedSuite->isPreparing() || null === $resultsJob) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        if ($serializedSuite->hasFailed()) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
