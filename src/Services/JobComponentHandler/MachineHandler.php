<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\RemoteRequestEntity;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;

class MachineHandler extends AbstractJobComponentHandler implements JobComponentHandlerInterface
{
    public function __construct(
        MachineRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    protected function getRemoteRequestEntity(): RemoteRequestEntity
    {
        return RemoteRequestEntity::MACHINE;
    }
}
