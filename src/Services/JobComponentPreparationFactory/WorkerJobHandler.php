<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationFactory;

use App\Enum\JobComponentName;
use App\Model\ComponentPreparation;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\WorkerComponentStateRepository;

class WorkerJobHandler extends AbstractJobComponentHandler implements JobComponentPreparationFactoryInterface
{
    private const JobComponentName JOB_COMPONENT = JobComponentName::WORKER_JOB;

    public function __construct(
        WorkerComponentStateRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function handles(JobComponentName $componentName): bool
    {
        return self::JOB_COMPONENT === $componentName;
    }

    public function getComponentPreparation(string $jobId): ComponentPreparation
    {
        return $this->doGetComponentPreparation($jobId, RemoteRequestType::createForWorkerJobCreation());
    }
}
