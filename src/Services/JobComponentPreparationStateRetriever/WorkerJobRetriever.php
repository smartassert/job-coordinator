<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationStateRetriever;

use App\Enum\PreparationState;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\WorkerComponentStateRepository;

class WorkerJobRetriever extends AbstractRetriever implements JobComponentPreparationStateRetrieverInterface
{
    public function __construct(
        WorkerComponentStateRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function get(string $jobId): PreparationState
    {
        return $this->doGet($jobId, RemoteRequestType::createForWorkerJobCreation());
    }
}
