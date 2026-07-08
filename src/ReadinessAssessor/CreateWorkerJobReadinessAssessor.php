<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

readonly class CreateWorkerJobReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
        private ResultsJobRepository $resultsJobRepository,
        private MachineRepository $machineRepository,
    ) {}

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        $serializedSuite = $this->serializedSuiteRepository->findByJobId($jobId);
        if (null === $serializedSuite || $serializedSuite->isPreparing()) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        $resultsJob = $this->resultsJobRepository->find($jobId);
        if (null === $resultsJob) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        if ($serializedSuite->hasFailed()) {
            return MessageHandlingReadiness::NEVER;
        }

        $machine = $this->machineRepository->find($jobId);
        if (null === $machine || false === $machine->getIsActive()) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
