<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\Machine;
use App\Entity\ResultsJob as ResultsJobEntity;
use App\Entity\SerializedSuite;
use App\Model\RemoteRequestCollection;
use App\Model\ResultsJob as ResultsJobModel;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

/**
 * @phpstan-import-type SerializedResultsJob from ResultsJobModel
 * @phpstan-import-type SerializedSerializedSuite from SerializedSuite
 * @phpstan-import-type SerializedMachine from Machine
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
            $data['results_job'] = (new ResultsJobModel($resultsJob))->toArray();
        }

        $serializedSuite = $this->serializedSuiteRepository->find($job->id);
        if ($serializedSuite instanceof SerializedSuite) {
            $data['serialized_suite'] = $serializedSuite->toArray();
        }

        $machine = $this->machineRepository->find($job->id);
        if ($machine instanceof Machine) {
            $data['machine'] = $machine->toArray();
        }

        $remoteRequests = $this->remoteRequestRepository->findBy(['jobId' => $job->id], ['id' => 'ASC']);
        $data['service_requests'] = (new RemoteRequestCollection($remoteRequests))->toArray();

        return $data;
    }
}
