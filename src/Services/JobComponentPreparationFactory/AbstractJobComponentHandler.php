<?php

declare(strict_types=1);

namespace App\Services\JobComponentPreparationFactory;

use App\Enum\PreparationState;
use App\Enum\RequestState;
use App\Model\ComponentPreparation;
use App\Model\RemoteRequestType;
use App\Repository\JobComponentRepositoryInterface;
use App\Repository\RemoteRequestRepository;

abstract class AbstractJobComponentHandler implements JobComponentPreparationFactoryInterface
{
    public function __construct(
        protected readonly JobComponentRepositoryInterface $entityRepository,
        protected readonly RemoteRequestRepository $remoteRequestRepository,
    ) {}

    protected function doGetComponentPreparation(string $jobId, RemoteRequestType $creationType): ComponentPreparation
    {
        if ($this->entityRepository->count(['jobId' => $jobId]) > 0) {
            return new ComponentPreparation(PreparationState::SUCCEEDED);
        }

        $remoteRequest = $this->remoteRequestRepository->findNewest($jobId, $creationType);
        if (null === $remoteRequest) {
            return new ComponentPreparation(PreparationState::PENDING);
        }

        if (RequestState::FAILED === $remoteRequest->getState()) {
            return new ComponentPreparation(
                PreparationState::FAILED,
                $remoteRequest->getFailure()
            );
        }

        return new ComponentPreparation(PreparationState::PREPARING);
    }
}
