<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Model\JobStatus;
use App\Model\RemoteRequestCollection;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

readonly class JobStatusFactory
{
    public function __construct(
        private PreparationStateFactory $preparationStateFactory,
        private ResultsJobRepository $resultsJobRepository,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private MachineRepository $machineRepository,
        private WorkerStateFactory $workerStateFactory,
        private RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    public function create(Job $job): JobStatus
    {
        return new JobStatus(
            $job,
            $this->preparationStateFactory->create($job),
            $this->resultsJobRepository->find($job->getId()),
            $this->serializedSuiteRepository->find($job->getId()),
            $this->machineRepository->find($job->getId()),
            $this->workerStateFactory->createForJob($job),
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findBy(['jobId' => $job->getId()], ['id' => 'ASC'])
            ),
        );
    }
}
