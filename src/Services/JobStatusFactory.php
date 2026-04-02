<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\JobComponents;
use App\Model\JobInterface;
use App\Model\JobStatus;
use App\Model\RemoteRequestCollection;
use App\Repository\RemoteRequestRepository;

readonly class JobStatusFactory
{
    public function __construct(
        private PreparationStateFactory $preparationStateFactory,
        private ResultsJobComponentFactory $resultsJobComponentFactory,
        private MachineComponentFactory $machineComponentFactory,
        private WorkerJobFactory $workerJobFactory,
        private SerializedSuiteComponentFactory $serializedSuiteComponentFactory,
        private RemoteRequestRepository $remoteRequestRepository,
        private MetaStateReducer $metaStateReducer,
    ) {}

    public function create(JobInterface $job): JobStatus
    {
        $preparationState = $this->preparationStateFactory->create($job);

        $resultsJob = $this->resultsJobComponentFactory->createForJob($job);
        $machine = $this->machineComponentFactory->createForJob($job);
        $workerJob = $this->workerJobFactory->createForJob($job);
        $serializedSuite = $this->serializedSuiteComponentFactory->createForJob($job);

        $jobMetaState = $this->metaStateReducer->reduce([
            $preparationState->getMetaState(),
            $resultsJob->getMetaState(),
            $serializedSuite->getMetaState(),
            $machine->getMetaState(),
            $workerJob->getMetaState(),
        ]);

        $components = new JobComponents([
            $resultsJob,
            $serializedSuite,
            $machine,
            $workerJob,
        ]);

        return new JobStatus(
            $job,
            $jobMetaState,
            $preparationState,
            $components,
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findBy(['jobId' => $job->getId()], ['id' => 'ASC'])
            ),
        );
    }
}
