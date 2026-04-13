<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationFactory;

use App\Model\JobComponent\Preparation;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\RequestStateRetriever\MachineRetriever;

class MachineFactory extends AbstractFactory
{
    public function __construct(
        MachineRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
        private readonly MachineRetriever $requestStateRetriever,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function create(string $jobId): Preparation
    {
        return new Preparation(
            $this->getPreparationState($jobId, RemoteRequestType::createForMachineCreation()),
            $this->requestStateRetriever->retrieve($jobId),
            $this->getRemoteRequestFailure($jobId, RemoteRequestType::createForMachineCreation()),
        );
    }
}
