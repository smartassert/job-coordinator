<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

class PreparationStateDeriver
{
    public function __construct(
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
        private readonly MachineRepository $machineRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    public function getForResultsJob(Job $job): PreparationState
    {
        if ($this->resultsJobRepository->count(['jobId' => $job->id]) > 0) {
            return PreparationState::SUCCEEDED;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($job, RemoteRequestType::RESULTS_CREATE);
        if (null === $remoteRequest) {
            return PreparationState::PENDING;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return PreparationState::FAILED;
        }

        return PreparationState::PREPARING;
    }

    public function getForSerializedSuite(Job $job): PreparationState
    {
        if ($this->serializedSuiteRepository->count(['jobId' => $job->id]) > 0) {
            return PreparationState::SUCCEEDED;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($job, RemoteRequestType::SERIALIZED_SUITE_CREATE);
        if (null === $remoteRequest) {
            return PreparationState::PENDING;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return PreparationState::FAILED;
        }

        return PreparationState::PREPARING;
    }

    public function getForMachine(Job $job): PreparationState
    {
        if ($this->machineRepository->count(['jobId' => $job->id]) > 0) {
            return PreparationState::SUCCEEDED;
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($job, RemoteRequestType::MACHINE_CREATE);
        if (null === $remoteRequest) {
            return PreparationState::PENDING;
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return PreparationState::FAILED;
        }

        return PreparationState::PREPARING;
    }
}
