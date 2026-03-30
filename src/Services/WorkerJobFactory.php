<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\WorkerComponentName;
use App\Model\JobInterface;
use App\Model\PendingWorkerComponentState;
use App\Model\WorkerComponentStateInterface;
use App\Model\WorkerJob;
use App\Repository\WorkerComponentStateRepository;

class WorkerJobFactory
{
    public function __construct(
        private readonly WorkerComponentStateRepository $workerComponentStateRepository,
    ) {}

    public function createForJob(JobInterface $job): WorkerJob
    {
        return new WorkerJob(
            $this->createComponentState($job, WorkerComponentName::APPLICATION),
            $this->createComponentState($job, WorkerComponentName::COMPILATION),
            $this->createComponentState($job, WorkerComponentName::EXECUTION),
            $this->createComponentState($job, WorkerComponentName::EVENT_DELIVERY),
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
