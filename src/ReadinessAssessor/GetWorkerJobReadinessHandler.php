<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Entity\WorkerComponentState;
use App\Enum\MessageHandlingReadiness;
use App\Model\RemoteRequestType;
use App\Repository\WorkerComponentStateRepository;

readonly class GetWorkerJobReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private WorkerComponentStateRepository $workerComponentStateRepository,
    ) {}

    public function handles(RemoteRequestType $type): bool
    {
        return RemoteRequestType::createForWorkerJobRetrieval()->equals($type);
    }

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        $applicationState = $this->workerComponentStateRepository->getApplicationState($jobId);
        if ($applicationState instanceof WorkerComponentState && $applicationState->isEndState()) {
            return MessageHandlingReadiness::NEVER;
        }

        return MessageHandlingReadiness::NOW;
    }
}
