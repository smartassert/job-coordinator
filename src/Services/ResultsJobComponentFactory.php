<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\JobComponent\ResultsJob;
use App\Model\JobInterface;
use App\Repository\ResultsJobRepository;

readonly class ResultsJobComponentFactory
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
    ) {}

    public function createForJob(JobInterface $job): ResultsJob
    {
        return new ResultsJob(
            $this->resultsJobRepository->find($job->getId()),
        );
    }
}
