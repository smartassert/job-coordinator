<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponentName;
use App\Model\JobComponent\Preparation;
use App\Model\JobComponent\SerializedSuite;
use App\Model\JobInterface;
use App\Model\RemoteRequestCollection;
use App\Repository\RemoteRequestRepository;
use App\Repository\SerializedSuiteRepository;
use App\Services\JobComponentHandler\SerializedSuiteHandler;

readonly class SerializedSuiteComponentFactory
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
        private RemoteRequestRepository $remoteRequestRepository,
        private SerializedSuiteHandler $handler,
    ) {}

    public function createForJob(JobInterface $job): SerializedSuite
    {
        $componentPreparation = $this->handler->getComponentPreparation($job->getId());
        $requestState = $this->handler->getRequestState($job->getId());

        return new SerializedSuite(
            $this->serializedSuiteRepository->find($job->getId()),
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findAllForJobAndComponent(
                    $job->getId(),
                    JobComponentName::SERIALIZED_SUITE
                )
            ),
            new Preparation($componentPreparation, $requestState),
        );
    }
}
