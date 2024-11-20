<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Entity\WorkerComponentState;
use App\Enum\MessageHandlingReadiness;
use App\Repository\WorkerComponentStateRepository;

readonly class GetWorkerJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private WorkerComponentStateRepository $workerComponentStateRepository,
    ) {
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
