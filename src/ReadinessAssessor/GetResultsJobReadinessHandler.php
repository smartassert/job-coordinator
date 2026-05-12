<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\JobComponentName;
use App\Enum\MessageHandlingReadiness;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestAction;
use App\Message\JobRemoteRequestMessageInterface;
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

    public function isReady(JobRemoteRequestMessageInterface $message): ?MessageHandlingReadiness
    {
        $requestType = $message->getRemoteRequestType();
        if (JobComponentName::RESULTS_JOB !== $requestType->componentName) {
            return null;
        }

        if (RemoteRequestAction::RETRIEVE !== $requestType->action) {
            return null;
        }

        $jobId = $message->getJobId();

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
