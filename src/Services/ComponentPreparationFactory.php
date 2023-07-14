<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\JobComponentName;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Model\JobComponent;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use App\Repository\WorkerComponentStateRepository;

class ComponentPreparationFactory
{
    public function __construct(
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
        private readonly MachineRepository $machineRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly WorkerComponentStateRepository $workerComponentStateRepository,
    ) {
    }

    /**
     * @return array{
     *   results_job: ComponentPreparation,
     *   serialized_suite: ComponentPreparation,
     *   machine: ComponentPreparation,
     *   worker_job: ComponentPreparation
     * }
     */
    public function getAll(Job $job): array
    {
        return [
            JobComponentName::RESULTS_JOB->value => $this->getForResultsJob($job),
            JobComponentName::SERIALIZED_SUITE->value => $this->getForSerializedSuite($job),
            JobComponentName::MACHINE->value => $this->getForMachine($job),
            JobComponentName::WORKER_JOB->value => $this->getForWorkerJob($job),
        ];
    }

    private function getForResultsJob(Job $job): ComponentPreparation
    {
        $jobComponent = new JobComponent(JobComponentName::RESULTS_JOB, RemoteRequestType::RESULTS_CREATE);

        if ($this->resultsJobRepository->count(['jobId' => $job->id]) > 0) {
            return new ComponentPreparation($jobComponent, PreparationState::SUCCEEDED);
        }

        return $this->deriveFromRemoteRequests($job, $jobComponent);
    }

    private function getForSerializedSuite(Job $job): ComponentPreparation
    {
        $jobComponent = new JobComponent(
            JobComponentName::SERIALIZED_SUITE,
            RemoteRequestType::SERIALIZED_SUITE_CREATE
        );

        if ($this->serializedSuiteRepository->count(['jobId' => $job->id]) > 0) {
            return new ComponentPreparation($jobComponent, PreparationState::SUCCEEDED);
        }

        return $this->deriveFromRemoteRequests($job, $jobComponent);
    }

    private function getForMachine(Job $job): ComponentPreparation
    {
        $jobComponent = new JobComponent(JobComponentName::MACHINE, RemoteRequestType::MACHINE_CREATE);

        if ($this->machineRepository->count(['jobId' => $job->id]) > 0) {
            return new ComponentPreparation($jobComponent, PreparationState::SUCCEEDED);
        }

        return $this->deriveFromRemoteRequests($job, $jobComponent);
    }

    private function getForWorkerJob(Job $job): ComponentPreparation
    {
        $jobComponent = new JobComponent(JobComponentName::WORKER_JOB, RemoteRequestType::MACHINE_START_JOB);

        $componentStates = $this->workerComponentStateRepository->getAllForJob($job);
        if ([] !== $componentStates) {
            return new ComponentPreparation($jobComponent, PreparationState::SUCCEEDED);
        }

        return $this->deriveFromRemoteRequests($job, $jobComponent);
    }

    private function deriveFromRemoteRequests(Job $job, JobComponent $jobComponent): ComponentPreparation
    {
        $remoteRequest = $this->remoteRequestRepository->findNewest($job, $jobComponent->requestType);
        if (null === $remoteRequest) {
            return new ComponentPreparation($jobComponent, PreparationState::PENDING);
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return new ComponentPreparation($jobComponent, PreparationState::FAILED, $remoteRequest->getFailure());
        }

        return new ComponentPreparation($jobComponent, PreparationState::PREPARING);
    }
}
