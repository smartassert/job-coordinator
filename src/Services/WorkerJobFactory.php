<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponentName;
use App\Enum\WorkerComponentName;
use App\Model\JobComponent\Preparation;
use App\Model\JobComponent\WorkerJob;
use App\Model\JobInterface;
use App\Model\PendingWorkerComponentState;
use App\Model\RemoteRequestCollection;
use App\Model\WorkerComponentStateInterface;
use App\Repository\RemoteRequestRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Repository\WorkerJobCreationFailureRepository;
use App\Services\JobComponentHandler\WorkerJobHandler;
use App\Services\RequestStateRetriever\WorkerJobRetriever;

readonly class WorkerJobFactory
{
    public function __construct(
        private WorkerComponentStateRepository $workerComponentStateRepository,
        private WorkerJobCreationFailureRepository $workerJobCreationFailureRepository,
        private RemoteRequestRepository $remoteRequestRepository,
        private WorkerJobHandler $handler,
        private WorkerJobRetriever $requestStateRetriever,
    ) {}

    public function createForJob(JobInterface $job): WorkerJob
    {
        $componentPreparation = $this->handler->getComponentPreparation($job->getId());
        $requestState = $this->requestStateRetriever->retrieve($job->getId());

        return new WorkerJob(
            $this->createComponentState($job, WorkerComponentName::APPLICATION),
            $this->createComponentState($job, WorkerComponentName::COMPILATION),
            $this->createComponentState($job, WorkerComponentName::EXECUTION),
            $this->createComponentState($job, WorkerComponentName::EVENT_DELIVERY),
            $this->workerJobCreationFailureRepository->find($job->getId()),
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findAllForJobAndComponent($job->getId(), JobComponentName::WORKER_JOB)
            ),
            new Preparation($componentPreparation, $requestState),
        );
    }

    private function createComponentState(
        JobInterface $job,
        WorkerComponentName $componentName,
    ): WorkerComponentStateInterface {
        $componentState = $this->workerComponentStateRepository->findOneBy([
            'jobId' => $job->getId(),
            'componentName' => $componentName,
        ]);

        if (null === $componentState) {
            $componentState = new PendingWorkerComponentState();
        }

        return $componentState;
    }
}
