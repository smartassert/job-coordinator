<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationFactory;

use App\Entity\RemoteRequestFailure;
use App\Enum\PreparationState;
use App\Enum\RequestState;
use App\Model\RemoteRequestType;
use App\Repository\JobComponentRepositoryInterface;
use App\Repository\RemoteRequestRepository;

abstract class AbstractFactory
{
    public function __construct(
        protected readonly JobComponentRepositoryInterface $entityRepository,
        protected readonly RemoteRequestRepository $remoteRequestRepository,
    ) {}

    protected function getRemoteRequestFailure(string $jobId, RemoteRequestType $creationType): ?RemoteRequestFailure
    {
        if ($this->entityRepository->count(['jobId' => $jobId]) > 0) {
            return null;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($jobId, $creationType);
        if (null === $remoteRequest) {
            return null;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return $remoteRequest->getFailure();
        }

        return null;
    }

    protected function getPreparationState(string $jobId, RemoteRequestType $creationType): PreparationState
    {
        if ($this->entityRepository->count(['jobId' => $jobId]) > 0) {
            return PreparationState::SUCCEEDED;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($jobId, $creationType);
        if (null === $remoteRequest) {
            return PreparationState::PENDING;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return PreparationState::FAILED;
        }

        return PreparationState::PREPARING;
    }
}
