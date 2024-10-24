<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\WorkerComponentName;
use App\Model\PendingWorkerComponentState;
use App\Model\WorkerComponentStateInterface;
use App\Model\WorkerState;
use App\Repository\WorkerComponentStateRepository;

class WorkerStateFactory
{
    public function __construct(
        private readonly WorkerComponentStateRepository $workerComponentStateRepository,
    ) {
    }

    public function createForJob(Job $job): WorkerState
    {
        return new WorkerState(
            $this->createComponentState($job, WorkerComponentName::APPLICATION),
            $this->createComponentState($job, WorkerComponentName::COMPILATION),
            $this->createComponentState($job, WorkerComponentName::EXECUTION),
            $this->createComponentState($job, WorkerComponentName::EVENT_DELIVERY),
        );
    }

    private function createComponentState(Job $job, WorkerComponentName $componentName): WorkerComponentStateInterface
    {
        $componentState = $this->workerComponentStateRepository->findOneBy([
            'jobId' => $job->id,
            'componentName' => $componentName,
        ]);

        if (null === $componentState) {
            $componentState = new PendingWorkerComponentState();
        }

        return $componentState;
    }
}
