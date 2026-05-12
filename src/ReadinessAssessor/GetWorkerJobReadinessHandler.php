<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Entity\WorkerComponentState;
use App\Enum\JobComponentName;
use App\Enum\MessageHandlingReadiness;
use App\Enum\RemoteRequestAction;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\WorkerComponentStateRepository;

readonly class GetWorkerJobReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private WorkerComponentStateRepository $workerComponentStateRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness
    {
        $requestType = $message->getRemoteRequestType();
        if (JobComponentName::WORKER_JOB !== $requestType->componentName) {
            return null;
        }

        if (RemoteRequestAction::RETRIEVE !== $requestType->action) {
            return null;
        }

        $applicationState = $this->workerComponentStateRepository->getApplicationState($message->getJobId());

        if (
            $applicationState instanceof WorkerComponentState
            && $applicationState->getMetaState()->ended
        ) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
