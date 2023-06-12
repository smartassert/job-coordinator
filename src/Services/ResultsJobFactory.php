<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Repository\ResultsJobRepository;

class ResultsJobFactory
{
    public function __construct(
        private readonly ResultsJobRepository $resultsJobRepository,
    ) {
    }

    /**
     * @param non-empty-string  $token
     * @param non-empty-string  $state
     * @param ?non-empty-string $endState
     */
    public function create(Job $job, string $token, string $state, ?string $endState): ResultsJob
    {
        $resultsJob = $this->resultsJobRepository->find($job->id);
        if (null === $resultsJob) {
            $resultsJob = new ResultsJob($job->id, $token, $state, $endState);
            $this->resultsJobRepository->save($resultsJob);
        }

        return $resultsJob;
    }
}
