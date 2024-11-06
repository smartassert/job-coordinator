<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\RemoteRequestEntity;
use App\Repository\RemoteRequestRepository;
use App\Repository\WorkerComponentStateRepository;

class WorkerJobHandler extends AbstractJobComponentHandler implements JobComponentHandlerInterface
{
    public function __construct(
        WorkerComponentStateRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    protected function getRemoteRequestEntity(): RemoteRequestEntity
    {
        return RemoteRequestEntity::WORKER_JOB;
    }
}
