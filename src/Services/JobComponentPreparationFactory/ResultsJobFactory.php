<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationFactory;

use App\Model\JobComponent\Preparation;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Services\RequestStateRetriever\ResultsJobRetriever;

class ResultsJobFactory extends AbstractFactory
{
    public function __construct(
        ResultsJobRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
        private readonly ResultsJobRetriever $requestStateRetriever,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function create(string $jobId): Preparation
    {
        return new Preparation(
            $this->doGetComponentPreparation($jobId, RemoteRequestType::createForResultsJobCreation()),
            $this->requestStateRetriever->retrieve($jobId),
        );
    }
}
