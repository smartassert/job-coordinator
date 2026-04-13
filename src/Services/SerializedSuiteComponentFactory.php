<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponentName;
use App\Model\JobComponent\SerializedSuite;
use App\Model\JobInterface;
use App\Model\RemoteRequestCollection;
use App\Repository\RemoteRequestRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\JobComponentPreparationFactory\SerializedSuiteFactory;

readonly class SerializedSuiteComponentFactory
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
        private RemoteRequestRepository $remoteRequestRepository,
        private SerializedSuiteFactory $preparationFactory,
    ) {}

    public function createForJob(JobInterface $job): SerializedSuite
    {
        return new SerializedSuite(
            $this->serializedSuiteRepository->find($job->getId()),
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findAllForJobAndComponent(
                    $job->getId(),
                    JobComponentName::SERIALIZED_SUITE
                )
            ),
            $this->preparationFactory->create($job->getId()),
        );
    }
}
