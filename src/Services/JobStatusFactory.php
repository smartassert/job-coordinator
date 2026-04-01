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
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

readonly class JobStatusFactory
{
    public function __construct(
        private PreparationStateFactory $preparationStateFactory,
        private ResultsJobRepository $resultsJobRepository,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private MachineComponentFactory $machineComponentFactory,
        private WorkerJobFactory $workerJobFactory,
        private RemoteRequestRepository $remoteRequestRepository,
        private MetaStateReducer $metaStateReducer,
    ) {}

    public function create(JobInterface $job): JobStatus
    {
        $preparationState = $this->preparationStateFactory->create($job);
        $resultsJob = $this->resultsJobRepository->find($job->getId());
        $serializedSuite = $this->serializedSuiteRepository->get($job->getId());

        $machine = $this->machineComponentFactory->createForJob($job);

        $workerJob = $this->workerJobFactory->createForJob($job);

        $jobMetaState = $this->metaStateReducer->reduce([
            $preparationState->getMetaState(),
            $resultsJob?->getMetaState(),
            $serializedSuite?->getMetaState(),
            $machine->getMetaState(),
            $workerJob->getMetaState(),
        ]);

        $components = new JobComponents([
            new NamedJobComponent(JobComponentName::RESULTS_JOB, $resultsJob),
            new NamedJobComponent(JobComponentName::SERIALIZED_SUITE, $serializedSuite),
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
