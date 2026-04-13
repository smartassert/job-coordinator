<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationStateRetriever;

use App\Enum\PreparationState;
use App\Enum\RequestState;
use App\Model\RemoteRequestType;
use App\Repository\JobComponentRepositoryInterface;
use App\Repository\RemoteRequestRepository;

abstract class AbstractRetriever implements JobComponentPreparationStateRetrieverInterface
{
    public function __construct(
        protected readonly JobComponentRepositoryInterface $entityRepository,
        protected readonly RemoteRequestRepository $remoteRequestRepository,
    ) {}

    protected function doGet(string $jobId, RemoteRequestType $creationType): PreparationState
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
