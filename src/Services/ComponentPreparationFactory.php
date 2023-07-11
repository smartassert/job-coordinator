<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\PreparationState;
use App\Enum\RemoteRequestType;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Repository\MachineRepository;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;

class ComponentPreparationFactory
{
    public function __construct(
        private readonly ResultsJobRepository $resultsJobRepository,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
        private readonly MachineRepository $machineRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    public function getForResultsJob(Job $job): ComponentPreparation
    {
        if ($this->resultsJobRepository->count(['jobId' => $job->id]) > 0) {
            return new ComponentPreparation(PreparationState::SUCCEEDED);
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($job, RemoteRequestType::RESULTS_CREATE);
        if (null === $remoteRequest) {
            return new ComponentPreparation(PreparationState::PENDING);
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return new ComponentPreparation(PreparationState::FAILED, $remoteRequest->getFailure());
        }

        return new ComponentPreparation(PreparationState::PREPARING);
    }

    public function getForSerializedSuite(Job $job): ComponentPreparation
    {
        if ($this->serializedSuiteRepository->count(['jobId' => $job->id]) > 0) {
            return new ComponentPreparation(PreparationState::SUCCEEDED);
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($job, RemoteRequestType::SERIALIZED_SUITE_CREATE);
        if (null === $remoteRequest) {
            return new ComponentPreparation(PreparationState::PENDING);
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return new ComponentPreparation(PreparationState::FAILED, $remoteRequest->getFailure());
        }

        return new ComponentPreparation(PreparationState::PREPARING);
    }

    public function getForMachine(Job $job): ComponentPreparation
    {
        if ($this->machineRepository->count(['jobId' => $job->id]) > 0) {
            return new ComponentPreparation(PreparationState::SUCCEEDED);
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($job, RemoteRequestType::MACHINE_CREATE);
        if (null === $remoteRequest) {
            return new ComponentPreparation(PreparationState::PENDING);
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return new ComponentPreparation(PreparationState::FAILED, $remoteRequest->getFailure());
        }

        return new ComponentPreparation(PreparationState::PREPARING);
    }
}
