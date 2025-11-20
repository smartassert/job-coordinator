<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\PreparationState;
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
    ) {}

    protected function doHasFailed(string $jobId, RemoteRequestType $creationType): ?bool
    {
        $remoteRequest = $this->remoteRequestRepository->findNewest($jobId, $creationType);
        if (null === $remoteRequest) {
            return null;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return true;
        }

        return false;
    }

    protected function doGetRequestState(string $jobId, RemoteRequestType $creationType): ?RequestState
    {
        if ($this->entityRepository->count(['jobId' => $jobId]) > 0) {
            return RequestState::SUCCEEDED;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($jobId, $creationType);

        return $remoteRequest?->getState();
    }

    protected function doGetComponentPreparation(string $jobId, RemoteRequestType $creationType): ?ComponentPreparation
    {
        if ($this->entityRepository->count(['jobId' => $jobId]) > 0) {
            return new ComponentPreparation($creationType->jobComponent, PreparationState::SUCCEEDED);
        }

        return $this->deriveFromRemoteRequests($jobId, $creationType);
    }

    private function deriveFromRemoteRequests(string $jobId, RemoteRequestType $creationType): ComponentPreparation
    {
        $remoteRequest = $this->remoteRequestRepository->findNewest($jobId, $creationType);

        if (null === $remoteRequest) {
            return new ComponentPreparation($creationType->jobComponent, PreparationState::PENDING);
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return new ComponentPreparation(
                $creationType->jobComponent,
                PreparationState::FAILED,
                $remoteRequest->getFailure()
            );
        }

        return new ComponentPreparation($creationType->jobComponent, PreparationState::PREPARING);
    }
}
