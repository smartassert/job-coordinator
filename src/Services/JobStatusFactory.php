<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\JobInterface;
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
        private MetaStateReducer $metaStateReducer,
    ) {}

    public function create(JobInterface $job): JobStatus
    {
        $preparationState = $this->preparationStateFactory->create($job);
        $resultsJob = $this->resultsJobRepository->find($job->getId());
        $serializedSuite = $this->serializedSuiteRepository->get($job->getId());
        $machine = $this->machineRepository->find($job->getId());
        $workerJobState = $this->workerStateFactory->createForJob($job);

        $jobMetaState = $this->metaStateReducer->reduce([
            $preparationState->getMetaState(),
            $resultsJob?->getMetaState(),
            $serializedSuite?->getMetaState(),
            $machine?->getMetaState(),
            $workerJobState->getMetaState(),
        ]);

        return new JobStatus(
            $job,
            $jobMetaState,
            $preparationState,
            $resultsJob,
            $serializedSuite,
            $machine,
            $workerJobState,
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findBy(['jobId' => $job->getId()], ['id' => 'ASC'])
            ),
        );
    }
}
