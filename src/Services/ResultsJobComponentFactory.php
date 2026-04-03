<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponentName;
use App\Model\JobComponent\Preparation;
use App\Model\JobComponent\ResultsJob;
use App\Model\JobInterface;
use App\Model\RemoteRequestCollection;
use App\Repository\RemoteRequestRepository;
use App\Repository\ResultsJobRepository;
use App\Services\JobComponentHandler\ResultsJobHandler;

readonly class ResultsJobComponentFactory
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
        private RemoteRequestRepository $remoteRequestRepository,
        private ResultsJobHandler $handler,
    ) {}

    public function createForJob(JobInterface $job): ResultsJob
    {
        $componentPreparation = $this->handler->getComponentPreparation($job->getId());
        $requestState = $this->handler->getRequestState($job->getId());

        return new ResultsJob(
            $this->resultsJobRepository->find($job->getId()),
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findAllForJobAndComponent($job->getId(), JobComponentName::RESULTS_JOB)
            ),
            new Preparation($componentPreparation, $requestState),
        );
    }
}
