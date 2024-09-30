<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Entity\Job;
use App\Enum\JobComponentName;
use App\Enum\PreparationState;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Model\JobComponent;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;

class MachineHandler implements JobComponentHandlerInterface
{
    public function __construct(
        private readonly MachineRepository $entityRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    public function getComponentPreparation(JobComponent $jobComponent, Job $job): ?ComponentPreparation
    {
        if (JobComponentName::MACHINE !== $jobComponent->name) {
            return null;
        }

        if ($this->entityRepository->count(['jobId' => $job->id]) > 0) {
            return new ComponentPreparation($jobComponent, PreparationState::SUCCEEDED);
        }

        return $this->deriveFromRemoteRequests($job, $jobComponent);
    }

    public function getRequestState(JobComponent $jobComponent, Job $job): ?RequestState
    {
        if (JobComponentName::MACHINE !== $jobComponent->name) {
            return null;
        }

        if ($this->entityRepository->count(['jobId' => $job->id]) > 0) {
            return RequestState::SUCCEEDED;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($job, $jobComponent->requestType);

        return $remoteRequest?->getState();
    }

    public function hasFailed(JobComponent $jobComponent, Job $job): ?bool
    {
        $remoteRequest = $this->remoteRequestRepository->findNewest($job, $jobComponent->requestType);
        if (null === $remoteRequest) {
            return null;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return true;
        }

        return false;
    }

    private function deriveFromRemoteRequests(Job $job, JobComponent $jobComponent): ComponentPreparation
    {
        $remoteRequest = $this->remoteRequestRepository->findNewest($job, $jobComponent->requestType);
        if (null === $remoteRequest) {
            return new ComponentPreparation($jobComponent, PreparationState::PENDING);
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return new ComponentPreparation($jobComponent, PreparationState::FAILED, $remoteRequest->getFailure());
        }

        return new ComponentPreparation($jobComponent, PreparationState::PREPARING);
    }
}
