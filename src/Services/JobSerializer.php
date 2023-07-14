<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\Machine as MachineEntity;
use App\Enum\JobComponentName;
use App\Enum\RemoteRequestType;
use App\Model\Machine as MachineModel;
use App\Model\PendingMachine;
use App\Model\PendingRemoteRequest;
use App\Model\PreparationState;
use App\Model\RemoteRequestCollection;
use App\Model\ResultsJob;
use App\Model\SerializedSuite;
use App\Model\SuccessfulRemoteRequest;
use App\Model\WorkerState;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

/**
 * @phpstan-import-type SerializedPreparationState from PreparationState
 * @phpstan-import-type SerializedResultsJob from ResultsJob
 * @phpstan-import-type SerializedSerializedSuite from SerializedSuite
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
        $resultsJobModel = new ResultsJob($resultsJob);
        $data[JobComponentName::RESULTS_JOB->value] = $resultsJobModel->toArray();

        $serializedSuite = $this->serializedSuiteRepository->find($job->id);
        $serializedSuiteModel = new SerializedSuite($serializedSuite);
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
