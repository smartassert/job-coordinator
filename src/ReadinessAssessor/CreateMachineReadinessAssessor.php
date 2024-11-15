<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

readonly class CreateMachineReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private ResultsJobRepository $resultsJobRepository,
    ) {
    }

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        if ($this->machineRepository->has($jobId)) {
            return MessageHandlingReadiness::NEVER;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($jobId);
        if (null === $serializedSuite || !$serializedSuite->isPrepared()) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        if (!$this->resultsJobRepository->has($jobId)) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
