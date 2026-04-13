<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationFactory;

use App\Model\JobComponent\Preparation;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\RequestStateRetriever\SerializedSuiteRetriever;

class SerializedSuiteFactory extends AbstractFactory
{
    public function __construct(
        SerializedSuiteRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
        private readonly SerializedSuiteRetriever $requestStateRetriever,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function create(string $jobId): Preparation
    {
        return new Preparation(
            $this->getPreparationState($jobId, RemoteRequestType::createForSerializedSuiteCreation()),
            $this->requestStateRetriever->retrieve($jobId),
            $this->getRemoteRequestFailure($jobId, RemoteRequestType::createForSerializedSuiteCreation()),
        );
    }
}
