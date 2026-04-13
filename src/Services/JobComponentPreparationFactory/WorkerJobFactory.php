<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationFactory;

use App\Model\JobComponent\Preparation;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\WorkerComponentStateRepository;
use App\Services\RequestStateRetriever\WorkerJobRetriever;

class WorkerJobFactory extends AbstractFactory
{
    public function __construct(
        WorkerComponentStateRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
        private readonly WorkerJobRetriever $requestStateRetriever,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function create(string $jobId): Preparation
    {
        return new Preparation(
            $this->doGetComponentPreparation($jobId, RemoteRequestType::createForWorkerJobCreation()),
            $this->requestStateRetriever->retrieve($jobId),
        );
    }
}
