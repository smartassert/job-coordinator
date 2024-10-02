<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use App\Enum\JobComponentName;
use App\Model\Machine;
use App\Model\RemoteRequestCollection;
use App\Model\WorkerState;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

/**
 * @phpstan-import-type SerializedPreparationState from PreparationStateFactory
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
     *   results_job: ResultsJob|null,
     *   serialized_suite: SerializedSuite|null,
     *   machine: SerializedMachine|null,
     *   worker_job: SerializedWorkerState,
     *   remote_requests?: SerializedRemoteRequestCollection
     *  }
     */
    public function serialize(Job $job): array
    {
        $data = $job->toArray();

        $data['preparation'] = $this->preparationStateFactory->create($job);
        $data[JobComponentName::RESULTS_JOB->value] = $this->resultsJobRepository->find($job->id);
        $data[JobComponentName::SERIALIZED_SUITE->value] = $this->serializedSuiteRepository->find($job->id);

        $machine = $this->machineRepository->find($job->id);
        if ($machine instanceof \App\Entity\Machine) {
            $machineModel = new Machine($machine);
            $data[JobComponentName::MACHINE->value] = $machineModel->toArray();
        } else {
            $data[JobComponentName::MACHINE->value] = null;
        }

        $workerState = $this->workerStateFactory->createForJob($job);
        $data[JobComponentName::WORKER_JOB->value] = $workerState->toArray();

        $remoteRequests = $this->remoteRequestRepository->findBy(['jobId' => $job->id], ['id' => 'ASC']);
        $data['service_requests'] = (new RemoteRequestCollection($remoteRequests))->toArray();

        return $data;
    }
}
