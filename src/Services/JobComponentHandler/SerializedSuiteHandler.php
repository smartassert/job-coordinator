<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponentName;
use App\Model\ComponentPreparation;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\SerializedSuiteRepository;

class SerializedSuiteHandler extends AbstractJobComponentHandler implements JobComponentHandlerInterface
{
    private const JobComponentName JOB_COMPONENT = JobComponentName::SERIALIZED_SUITE;

    public function __construct(
        SerializedSuiteRepository $entityRepository,
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
        return $this->doGetComponentPreparation($jobId, RemoteRequestType::createForSerializedSuiteCreation());
    }
}
