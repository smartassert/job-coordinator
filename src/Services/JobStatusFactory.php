<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponentName;
use App\Model\JobComponents;
use App\Model\JobInterface;
use App\Model\JobStatus;
use App\Model\NamedJobComponent;
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

        $components = new JobComponents([
            new NamedJobComponent(JobComponentName::RESULTS_JOB, $resultsJob),
            new NamedJobComponent(JobComponentName::SERIALIZED_SUITE, $serializedSuite),
            new NamedJobComponent(JobComponentName::MACHINE, $machine),
            new NamedJobComponent(JobComponentName::WORKER_JOB, $workerJobState),
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
