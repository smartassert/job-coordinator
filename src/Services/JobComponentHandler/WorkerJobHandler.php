<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\WorkerComponentStateRepository;

class WorkerJobHandler extends AbstractJobComponentHandler implements JobComponentHandlerInterface
{
    private const JobComponent JOB_COMPONENT = JobComponent::WORKER_JOB;

    public function __construct(
        WorkerComponentStateRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function handles(JobComponent $jobComponent): bool
    {
        return self::JOB_COMPONENT === $jobComponent;
    }

    public function getComponentPreparation(string $jobId): ?ComponentPreparation
    {
        return $this->doGetComponentPreparation($jobId, RemoteRequestType::createForWorkerJobCreation());
    }

    public function getRequestState(string $jobId): ?RequestState
    {
        return $this->doGetRequestState($jobId, RemoteRequestType::createForWorkerJobCreation());
    }

    public function hasFailed(string $jobId): ?bool
    {
        return $this->doHasFailed($jobId, RemoteRequestType::createForWorkerJobCreation());
    }
}
