<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

readonly class CreateWorkerJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): MessageHandlingReadiness
    {
        $jobId = $message->getJobId();

        $serializedSuite = $this->serializedSuiteRepository->get($jobId);
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
