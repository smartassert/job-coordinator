<?php

declare(strict_types=1);

namespace App\Services\RequestStateRetriever;

use App\Enum\RequestState;
use App\Model\RemoteRequestType;
use App\Repository\JobComponentRepositoryInterface;
use App\Repository\RemoteRequestRepository;

abstract readonly class AbstractRetriever
{
    public function __construct(
        private JobComponentRepositoryInterface $entityRepository,
        private RemoteRequestRepository $remoteRequestRepository,
    ) {}

    protected function doRetrieve(string $jobId, RemoteRequestType $creationType): RequestState
    {
        if ($this->entityRepository->count(['jobId' => $jobId]) > 0) {
            return RequestState::SUCCEEDED;
        }

        $requestState = $this->remoteRequestRepository->findNewest($jobId, $creationType)?->getState();

        return null === $requestState ? RequestState::PENDING : $requestState;
    }
}
