<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationFactory;

use App\Enum\JobComponentName;
use App\Model\ComponentPreparation;
use App\Model\RemoteRequestType;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;

class MachineHandler extends AbstractJobComponentHandler implements JobComponentHandlerInterface
{
    private const JobComponentName JOB_COMPONENT = JobComponentName::MACHINE;

    public function __construct(
        MachineRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function handles(JobComponentName $componentName): bool
    {
        return self::JOB_COMPONENT === $componentName;
    }

    public function getComponentPreparation(string $jobId): ComponentPreparation
    {
        return $this->doGetComponentPreparation($jobId, RemoteRequestType::createForMachineCreation());
    }
}
