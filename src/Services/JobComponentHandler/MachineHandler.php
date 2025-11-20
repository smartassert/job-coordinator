<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Model\RemoteRequestType;
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

    public function handles(JobComponent $jobComponent): bool
    {
        return JobComponent::MACHINE === $jobComponent;
    }

    public function getComponentPreparation(string $jobId): ?ComponentPreparation
    {
        return $this->doGetComponentPreparation($jobId, JobComponent::MACHINE);
    }

    public function getRequestState(string $jobId): ?RequestState
    {
        return $this->doGetRequestState(
            $jobId,
            new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::CREATE)
        );
    }

    public function hasFailed(string $jobId): ?bool
    {
        return $this->doHasFailed(
            $jobId,
            new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::CREATE)
        );
    }

    protected function getJobComponent(): JobComponent
    {
        return JobComponent::MACHINE;
    }
}
