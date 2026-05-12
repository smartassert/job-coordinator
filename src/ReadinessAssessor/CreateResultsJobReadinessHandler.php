<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\JobComponentName;
use App\Enum\MessageHandlingReadiness;
use App\Enum\RemoteRequestAction;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\ResultsJobRepository;

readonly class CreateResultsJobReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness
    {
        $requestType = $message->getRemoteRequestType();
        if (JobComponentName::RESULTS_JOB !== $requestType->componentName) {
            return null;
        }

        if (RemoteRequestAction::CREATE !== $requestType->action) {
            return null;
        }

        if ($this->resultsJobRepository->has($message->getJobId())) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
