<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\JobComponentName;
use App\Model\Machine;
use App\Model\PreparationState;
use App\Model\RemoteRequestCollection;
use App\Model\ResultsJob;
use App\Model\SerializedSuite;
use App\Model\WorkerState;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

/**
 * @phpstan-import-type SerializedPreparationState from PreparationState
 * @phpstan-import-type SerializedResultsJob from ResultsJob
 * @phpstan-import-type SerializedSerializedSuite from SerializedSuite
 * @phpstan-import-type SerializedMachine from Machine
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
     *   results_job: SerializedResultsJob|null,
     *   serialized_suite: SerializedSerializedSuite|null,
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
        if ($resultsJob instanceof \App\Entity\ResultsJob) {
            $resultsJobModel = new ResultsJob($resultsJob);
            $data[JobComponentName::RESULTS_JOB->value] = $resultsJobModel->toArray();
        } else {
            $data[JobComponentName::RESULTS_JOB->value] = null;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($job->id);
        if ($serializedSuite instanceof \App\Entity\SerializedSuite) {
            $serializedSuiteModel = new SerializedSuite($serializedSuite);
            $data[JobComponentName::SERIALIZED_SUITE->value] = $serializedSuiteModel->toArray();
        } else {
            $data[JobComponentName::SERIALIZED_SUITE->value] = null;
        }

        $machine = $this->machineRepository->find($job->id);
        $machineModel = new Machine($machine);
        $data[JobComponentName::MACHINE->value] = $machineModel->toArray();

        $workerState = $this->workerStateFactory->createForJob($job);
        $data[JobComponentName::WORKER_JOB->value] = $workerState->toArray();

        $remoteRequests = $this->remoteRequestRepository->findBy(['jobId' => $job->id], ['id' => 'ASC']);
        $data['service_requests'] = (new RemoteRequestCollection($remoteRequests))->toArray();

        return $data;
    }
}
