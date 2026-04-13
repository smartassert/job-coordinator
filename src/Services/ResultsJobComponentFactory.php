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
use App\Services\JobComponentPreparationFactory\ResultsJobFactory;
use App\Services\RequestStateRetriever\ResultsJobRetriever;

readonly class ResultsJobComponentFactory
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
        private RemoteRequestRepository $remoteRequestRepository,
        private ResultsJobFactory $componentPreparationFactory,
        private ResultsJobRetriever $requestStateRetriever,
    ) {}

    public function createForJob(JobInterface $job): ResultsJob
    {
        $componentPreparation = $this->componentPreparationFactory->getComponentPreparation($job->getId());
        $requestState = $this->requestStateRetriever->retrieve($job->getId());

        return new ResultsJob(
            $this->resultsJobRepository->find($job->getId()),
            new RemoteRequestCollection(
                $this->remoteRequestRepository->findAllForJobAndComponent($job->getId(), JobComponentName::RESULTS_JOB)
            ),
            new Preparation($componentPreparation, $requestState),
        );
    }
}
