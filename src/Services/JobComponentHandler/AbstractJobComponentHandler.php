<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestAction;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Model\JobInterface;
use App\Model\RemoteRequestType;
use App\Repository\JobComponentRepositoryInterface;
use App\Repository\RemoteRequestRepository;

abstract class AbstractJobComponentHandler implements JobComponentHandlerInterface
{
    public function __construct(
        protected readonly JobComponentRepositoryInterface $entityRepository,
        protected readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    public function getComponentPreparation(JobComponent $jobComponent, JobInterface $job): ?ComponentPreparation
    {
        if ($this->getJobComponent() !== $jobComponent) {
            return null;
        }

        if ($this->entityRepository->count(['jobId' => $job->getId()]) > 0) {
            return new ComponentPreparation($jobComponent, PreparationState::SUCCEEDED);
        }

        return $this->deriveFromRemoteRequests($job, $jobComponent);
    }

    public function getRequestState(JobComponent $jobComponent, JobInterface $job): ?RequestState
    {
        if ($this->getJobComponent() !== $jobComponent) {
            return null;
        }

        if ($this->entityRepository->count(['jobId' => $job->getId()]) > 0) {
            return RequestState::SUCCEEDED;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest(
            $job,
            new RemoteRequestType($jobComponent, RemoteRequestAction::CREATE),
        );

        return $remoteRequest?->getState();
    }

    public function hasFailed(JobComponent $jobComponent, JobInterface $job): ?bool
    {
        if ($this->getJobComponent() !== $jobComponent) {
            return null;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest(
            $job,
            new RemoteRequestType($jobComponent, RemoteRequestAction::CREATE),
        );

        if (null === $remoteRequest) {
            return null;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return true;
        }

        return false;
    }

    abstract protected function getJobComponent(): JobComponent;

    private function deriveFromRemoteRequests(JobInterface $job, JobComponent $jobComponent): ComponentPreparation
    {
        $remoteRequest = $this->remoteRequestRepository->findNewest(
            $job,
            new RemoteRequestType($jobComponent, RemoteRequestAction::CREATE),
        );

        if (null === $remoteRequest) {
            return new ComponentPreparation($jobComponent, PreparationState::PENDING);
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return new ComponentPreparation(
                $jobComponent,
                PreparationState::FAILED,
                $remoteRequest->getFailure()
            );
        }

        return new ComponentPreparation($jobComponent, PreparationState::PREPARING);
    }
}
