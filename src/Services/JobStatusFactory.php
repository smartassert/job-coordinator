<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponentName;
use App\Model\JobComponent\NamedJobComponent;
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

        $resultsJobComponent = $resultsJob->isEmpty()
            ? new NamedJobComponent(JobComponentName::RESULTS_JOB, null)
            : $resultsJob;

        $machineComponent = $machine->isEmpty()
            ? new NamedJobComponent(JobComponentName::MACHINE, null)
            : $machine;

        $serializedSuiteComponent = $serializedSuite->isEmpty()
            ? new NamedJobComponent(JobComponentName::SERIALIZED_SUITE, null)
            : $serializedSuite;

        $components = new JobComponents([
            $resultsJobComponent,
            $serializedSuiteComponent,
            $machineComponent,
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
