<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\PreparationState as PreparationStateEnum;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;

class PreparationStateDeriver
{
    public function __construct(
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    public function getForResultsJob(Job $job): PreparationStateEnum
    {
        if ($this->resultsJobRepository->count(['jobId' => $job->id]) > 0) {
            return PreparationStateEnum::SUCCEEDED;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($job, RemoteRequestType::RESULTS_CREATE);
        if (null === $remoteRequest) {
            return PreparationStateEnum::PENDING;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return PreparationStateEnum::FAILED;
        }

        return PreparationStateEnum::PREPARING;
    }
}
