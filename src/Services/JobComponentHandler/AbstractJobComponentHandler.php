<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Entity\Job;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
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

    public function getComponentPreparation(RemoteRequestEntity $remoteRequestEntity, Job $job): ?ComponentPreparation
    {
        if ($this->getRemoteRequestEntity() !== $remoteRequestEntity) {
            return null;
        }

        if ($this->entityRepository->count(['jobId' => $job->id]) > 0) {
            return new ComponentPreparation($remoteRequestEntity, PreparationState::SUCCEEDED);
        }

        return $this->deriveFromRemoteRequests($job, $remoteRequestEntity);
    }

    public function getRequestState(RemoteRequestEntity $remoteRequestEntity, Job $job): ?RequestState
    {
        if ($this->getRemoteRequestEntity() !== $remoteRequestEntity) {
            return null;
        }

        if ($this->entityRepository->count(['jobId' => $job->id]) > 0) {
            return RequestState::SUCCEEDED;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest(
            $job,
            new RemoteRequestType($remoteRequestEntity, RemoteRequestAction::CREATE),
        );

        return $remoteRequest?->getState();
    }

    public function hasFailed(RemoteRequestEntity $remoteRequestEntity, Job $job): ?bool
    {
        if ($this->getRemoteRequestEntity() !== $remoteRequestEntity) {
            return null;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest(
            $job,
            new RemoteRequestType($remoteRequestEntity, RemoteRequestAction::CREATE),
        );

        if (null === $remoteRequest) {
            return null;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return true;
        }

        return false;
    }

    abstract protected function getRemoteRequestEntity(): RemoteRequestEntity;

    private function deriveFromRemoteRequests(Job $job, RemoteRequestEntity $remoteRequestEntity): ComponentPreparation
    {
        $remoteRequest = $this->remoteRequestRepository->findNewest(
            $job,
            new RemoteRequestType($remoteRequestEntity, RemoteRequestAction::CREATE),
        );

        if (null === $remoteRequest) {
            return new ComponentPreparation($remoteRequestEntity, PreparationState::PENDING);
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return new ComponentPreparation(
                $remoteRequestEntity,
                PreparationState::FAILED,
                $remoteRequest->getFailure()
            );
        }

        return new ComponentPreparation($remoteRequestEntity, PreparationState::PREPARING);
    }
}
