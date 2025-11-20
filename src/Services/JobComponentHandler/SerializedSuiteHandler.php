<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;
use App\Repository\SerializedSuiteRepository;

class SerializedSuiteHandler extends AbstractJobComponentHandler implements JobComponentHandlerInterface
{
    private const JobComponent JOB_COMPONENT = JobComponent::SERIALIZED_SUITE;

    public function __construct(
        SerializedSuiteRepository $entityRepository,
        RemoteRequestRepository $remoteRequestRepository,
    ) {
        parent::__construct($entityRepository, $remoteRequestRepository);
    }

    public function handles(JobComponent $jobComponent): bool
    {
        return self::JOB_COMPONENT === $jobComponent;
    }

    public function getComponentPreparation(string $jobId): ?ComponentPreparation
    {
        return $this->doGetComponentPreparation($jobId, RemoteRequestType::createForSerializedSuiteCreation());
    }

    public function getRequestState(string $jobId): ?RequestState
    {
        return $this->doGetRequestState($jobId, RemoteRequestType::createForSerializedSuiteCreation());
    }

    public function hasFailed(string $jobId): ?bool
    {
        return $this->doHasFailed($jobId, RemoteRequestType::createForSerializedSuiteCreation());
    }
}
