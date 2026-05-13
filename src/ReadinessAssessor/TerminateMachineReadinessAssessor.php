<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Services\JobComponentPreparationFactory\WorkerJobFactory as WorkerJobPreparationFactory;

readonly class TerminateMachineReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
        private ResultsJobRepository $resultsJobRepository,
        private WorkerJobPreparationFactory $workerJobPreparationFactory,
    ) {}

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        if (!$this->machineRepository->has($jobId)) {
            return MessageHandlingReadiness::NEVER;
        }

        $preparation = $this->workerJobPreparationFactory->create($jobId);
        if ($preparation->hasFailure()) {
            return MessageHandlingReadiness::NOW;
        }

        $resultsJob = $this->resultsJobRepository->find($jobId);
        if (null === $resultsJob || !$resultsJob->hasEndState()) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
