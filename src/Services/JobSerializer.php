<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\Machine as MachineEntity;
use App\Entity\ResultsJob as ResultsJobEntity;
use App\Entity\SerializedSuite as SerializedSuiteEntity;
use App\Enum\RequestState;
use App\Model\Machine as MachineModel;
use App\Model\RemoteRequestCollection;
use App\Model\ResultsJob as ResultsJobModel;
use App\Model\SerializableRemoteRequest;
use App\Model\SerializedSuite as SerializedSuiteModel;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

/**
 * @phpstan-import-type SerializedResultsJob from ResultsJobModel
 * @phpstan-import-type SerializedSerializedSuite from SerializedSuiteModel
 * @phpstan-import-type SerializedMachine from MachineModel
 * @phpstan-import-type SerializedRemoteRequestCollection from RemoteRequestCollection
 */
class JobSerializer
{
    public function __construct(
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
        private readonly MachineRepository $machineRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    /**
     * @return array{
     *   id: non-empty-string,
     *   suite_id: non-empty-string,
     *   maximum_duration_in_seconds: positive-int,
     *   results_job?: SerializedResultsJob,
     *   serialized_suite?: SerializedSerializedSuite,
     *   machine?: SerializedMachine,
     *   remote_requests?: SerializedRemoteRequestCollection
     *  }
     */
    public function serialize(Job $job): array
    {
        $data = $job->toArray();

        $resultsJob = $this->resultsJobRepository->find($job->id);
        if ($resultsJob instanceof ResultsJobEntity) {
            $resultsJobModel = new ResultsJobModel(
                $resultsJob,
                new SerializableRemoteRequest(RequestState::SUCCEEDED),
            );

            $data['results_job'] = $resultsJobModel->toArray();
        }

        $serializedSuite = $this->serializedSuiteRepository->find($job->id);
        if ($serializedSuite instanceof SerializedSuiteEntity) {
            $serializedSuiteModel = new SerializedSuiteModel(
                $serializedSuite,
                new SerializableRemoteRequest(RequestState::SUCCEEDED),
            );

            $data['serialized_suite'] = $serializedSuiteModel->toArray();
        }

        $machine = $this->machineRepository->find($job->id);
        if ($machine instanceof MachineEntity) {
            $data['machine'] = (new MachineModel($machine))->toArray();
        }

        $remoteRequests = $this->remoteRequestRepository->findBy(['jobId' => $job->id], ['id' => 'ASC']);
        $data['service_requests'] = (new RemoteRequestCollection($remoteRequests))->toArray();

        return $data;
    }
}
