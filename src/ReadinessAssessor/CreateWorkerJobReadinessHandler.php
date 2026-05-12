<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\JobComponentName;
use App\Enum\MessageHandlingReadiness;
use App\Enum\RemoteRequestAction;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

readonly class CreateWorkerJobReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness
    {
        $requestType = $message->getRemoteRequestType();
        if (JobComponentName::WORKER_JOB !== $requestType->componentName) {
            return null;
        }

        if (RemoteRequestAction::CREATE !== $requestType->action) {
            return null;
        }

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
