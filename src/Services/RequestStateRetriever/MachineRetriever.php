<?php

declare(strict_types=1);

namespace App\Services\RequestStateRetriever;

use App\Enum\RequestState;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;

readonly class MachineRetriever extends AbstractRetriever
{
    public function __construct(
        MachineRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function retrieve(string $jobId): RequestState
    {
        return $this->doRetrieve($jobId, RemoteRequestType::createForMachineCreation());
    }
}
