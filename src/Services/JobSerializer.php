<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\Machine as MachineEntity;
use App\Entity\ResultsJob as ResultsJobEntity;
use App\Entity\SerializedSuite as SerializedSuiteEntity;
use App\Enum\JobComponentName;
use App\Enum\RemoteRequestType;
use App\Model\Machine as MachineModel;
use App\Model\PendingMachine;
use App\Model\PendingRemoteRequest;
use App\Model\PendingResultsJob;
use App\Model\PendingSerializedSuite;
use App\Model\PreparationState;
use App\Model\RemoteRequestCollection;
use App\Model\ResultsJob as ResultsJobModel;
use App\Model\SerializedSuite as SerializedSuiteModel;
use App\Model\SuccessfulRemoteRequest;
use App\Model\WorkerState;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

/**
 * @phpstan-import-type SerializedPreparationState from PreparationState
 * @phpstan-import-type SerializedResultsJob from ResultsJobModel
 * @phpstan-import-type SerializedSerializedSuite from SerializedSuiteModel
 * @phpstan-import-type SerializedMachine from MachineModel
 * @phpstan-import-type SerializedWorkerState from WorkerState
 * @phpstan-import-type SerializedRemoteRequestCollection from RemoteRequestCollection
 */
class JobSerializer
{
    public function __construct(
        private readonly PreparationStateFactory $preparationStateFactory,
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
        private readonly MachineRepository $machineRepository,
        private readonly WorkerStateFactory $workerStateFactory,
        private readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    /**
     * @return array{
     *   id: non-empty-string,
     *   suite_id: non-empty-string,
     *   maximum_duration_in_seconds: positive-int,
     *   preparation: SerializedPreparationState,
     *   results_job: SerializedResultsJob,
     *   serialized_suite: SerializedSerializedSuite,
     *   machine: SerializedMachine,
     *   worker_job: SerializedWorkerState,
     *   remote_requests?: SerializedRemoteRequestCollection
     *  }
     */
    public function serialize(Job $job): array
    {
        $data = $job->toArray();
        $preparationState = $this->preparationStateFactory->create($job);
        $data['preparation'] = $preparationState->toArray();

        $resultsJob = $this->resultsJobRepository->find($job->id);
        if ($resultsJob instanceof ResultsJobEntity) {
            $resultsJobRequest = new SuccessfulRemoteRequest();
        } else {
            $resultsJobRequest = $this->remoteRequestRepository->findNewest(
                $job,
                RemoteRequestType::RESULTS_CREATE
            );

            if (null === $resultsJobRequest) {
                $resultsJobRequest = new PendingRemoteRequest();
            }

            $resultsJob = new PendingResultsJob();
        }

        $resultsJobModel = new ResultsJobModel($resultsJob, $resultsJobRequest);
        $data[JobComponentName::RESULTS_JOB->value] = $resultsJobModel->toArray();

        $serializedSuite = $this->serializedSuiteRepository->find($job->id);
        if ($serializedSuite instanceof SerializedSuiteEntity) {
            $serializedSuiteRequest = new SuccessfulRemoteRequest();
        } else {
            $serializedSuiteRequest = $this->remoteRequestRepository->findNewest(
                $job,
                RemoteRequestType::SERIALIZED_SUITE_CREATE
            );

            if (null === $serializedSuiteRequest) {
                $serializedSuiteRequest = new PendingRemoteRequest();
            }

            $serializedSuite = new PendingSerializedSuite();
        }

        $serializedSuiteModel = new SerializedSuiteModel($serializedSuite, $serializedSuiteRequest);
        $data[JobComponentName::SERIALIZED_SUITE->value] = $serializedSuiteModel->toArray();

        $machine = $this->machineRepository->find($job->id);
        if ($machine instanceof MachineEntity) {
            $machineRequest = new SuccessfulRemoteRequest();
        } else {
            $machineRequest = $this->remoteRequestRepository->findNewest($job, RemoteRequestType::MACHINE_CREATE);
            if (null === $machineRequest) {
                $machineRequest = new PendingRemoteRequest();
            }

            $machine = new PendingMachine();
        }

        $machineModel = new MachineModel($machine, $machineRequest);
        $data[JobComponentName::MACHINE->value] = $machineModel->toArray();

        $workerState = $this->workerStateFactory->createForJob($job);
        $data[JobComponentName::WORKER_JOB->value] = $workerState->toArray();

        $remoteRequests = $this->remoteRequestRepository->findBy(['jobId' => $job->id], ['id' => 'ASC']);
        $data['service_requests'] = (new RemoteRequestCollection($remoteRequests))->toArray();

        return $data;
    }
}
