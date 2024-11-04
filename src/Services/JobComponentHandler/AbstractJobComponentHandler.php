<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Entity\Job;
use App\Enum\JobComponentName;
use App\Enum\PreparationState;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Model\JobComponent;
use App\Repository\FooRepositoryInterface;
use App\Repository\RemoteRequestRepository;

abstract class AbstractJobComponentHandler implements JobComponentHandlerInterface
{
    public function __construct(
        protected readonly FooRepositoryInterface $entityRepository,
        protected readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    public function getComponentPreparation(JobComponent $jobComponent, Job $job): ?ComponentPreparation
    {
        if ($this->getJobComponentName() !== $jobComponent->name) {
            return null;
        }

        if ($this->entityRepository->count(['jobId' => $job->id]) > 0) {
            return new ComponentPreparation($jobComponent, PreparationState::SUCCEEDED);
        }

        return $this->deriveFromRemoteRequests($job, $jobComponent);
    }

    public function getRequestState(JobComponent $jobComponent, Job $job): ?RequestState
    {
        if ($this->getJobComponentName() !== $jobComponent->name) {
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
        if ($this->getJobComponentName() !== $jobComponent->name) {
            return null;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($job, $jobComponent->requestType);
        if (null === $remoteRequest) {
            return null;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return true;
        }

        return false;
    }

    abstract protected function getJobComponentName(): JobComponentName;

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
