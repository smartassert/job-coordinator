<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;

readonly class TerminateMachineReadinessAssessor implements ReadinessAssessorInterface
{
    public function __construct(
        private MachineRepository $machineRepository,
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        if (!$this->machineRepository->has($jobId)) {
            return MessageHandlingReadiness::NEVER;
        }

        $resultsJob = $this->resultsJobRepository->find($jobId);
        if (null === $resultsJob || !$resultsJob->hasEndState()) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
