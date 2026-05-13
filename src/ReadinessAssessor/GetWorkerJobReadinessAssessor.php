<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Entity\WorkerComponentState;
use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\WorkerComponentStateRepository;

readonly class GetWorkerJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private WorkerComponentStateRepository $workerComponentStateRepository,
    ) {}

    public function isReady(JobRemoteRequestMessageInterface $message): MessageHandlingReadiness
    {
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
