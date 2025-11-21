<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;
use App\Enum\PreparationState;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\ResultsJobRepository;
use App\Services\PreparationStateFactory;

readonly class GetResultsJobReadinessHandler implements ReadinessHandlerInterface
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
        private PreparationStateFactory $preparationStateFactory,
        private MachineRepository $machineRepository,
    ) {}

    public function handles(RemoteRequestType $type): bool
    {
        return RemoteRequestType::createForResultsJobRetrieval()->equals($type);
    }

    public function isReady(string $jobId): MessageHandlingReadiness
    {
        $resultsJob = $this->resultsJobRepository->find($jobId);
        if (null === $resultsJob) {
            return MessageHandlingReadiness::NEVER;
        }

        if ($resultsJob->hasEndState()) {
            return MessageHandlingReadiness::NEVER;
        }

        $preparationState = $this->preparationStateFactory->createState($jobId);
        if (PreparationState::FAILED === $preparationState) {
            return MessageHandlingReadiness::NEVER;
        }

        $machine = $this->machineRepository->find($jobId);
        if (null === $machine) {
            return MessageHandlingReadiness::EVENTUALLY;
        }

        return MessageHandlingReadiness::NOW;
    }
}
