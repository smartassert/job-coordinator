<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\JobInterface;
use App\Model\JobStatus;
use App\Model\RemoteRequestCollection;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;

readonly class JobStatusFactory
{
    public function __construct(
        private PreparationStateFactory $preparationStateFactory,
        private ResultsJobRepository $resultsJobRepository,
        private SerializedSuiteStore $serializedSuiteStore,
        private MachineRepository $machineRepository,
        private WorkerStateFactory $workerStateFactory,
        private RemoteRequestRepository $remoteRequestRepository,
    ) {}

    public function create(JobInterface $job): JobStatus
    {
        return new JobStatus(
            $job,
            $this->preparationStateFactory->create($job),
            $this->resultsJobRepository->find($job->getId()),
            $this->serializedSuiteStore->retrieve($job->getId()),
            $this->machineRepository->find($job->getId()),
            $this->workerStateFactory->createForJob($job),
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findBy(['jobId' => $job->getId()], ['id' => 'ASC'])
            ),
        );
    }
}
