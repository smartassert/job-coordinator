<?php

declare(strict_types=1);

namespace App\Services\JobComponentHandler;

use App\Enum\JobComponent;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestAction;
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

    public function hasFailed(string $jobId): ?bool
    {
        $remoteRequest = $this->remoteRequestRepository->findNewest(
            $jobId,
            new RemoteRequestType($this->getJobComponent(), RemoteRequestAction::CREATE),
        );

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

    protected function doGetComponentPreparation(string $jobId, JobComponent $jobComponent): ?ComponentPreparation
    {
        if ($this->entityRepository->count(['jobId' => $jobId]) > 0) {
            return new ComponentPreparation($jobComponent, PreparationState::SUCCEEDED);
        }

        return $this->deriveFromRemoteRequests($jobId, $jobComponent);
    }

    abstract protected function getJobComponent(): JobComponent;

    private function deriveFromRemoteRequests(string $jobId, JobComponent $jobComponent): ComponentPreparation
    {
        $remoteRequest = $this->remoteRequestRepository->findNewest(
            $jobId,
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
