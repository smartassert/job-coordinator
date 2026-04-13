<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationStateRetriever;

use App\Enum\PreparationState;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;

class MachineRetriever extends AbstractRetriever implements JobComponentPreparationStateRetrieverInterface
{
    public function __construct(
        MachineRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function get(string $jobId): PreparationState
    {
        return $this->doGet($jobId, RemoteRequestType::createForMachineCreation());
    }
}
