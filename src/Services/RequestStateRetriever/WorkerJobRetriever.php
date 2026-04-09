<?php

declare(strict_types=1);

namespace App\Services\RequestStateRetriever;

use App\Enum\RequestState;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\WorkerComponentStateRepository;

readonly class WorkerJobRetriever extends AbstractRetriever
{
    public function __construct(
        WorkerComponentStateRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function retrieve(string $jobId): RequestState
    {
        return $this->doRetrieve($jobId, RemoteRequestType::createForWorkerJobCreation());
    }
}
