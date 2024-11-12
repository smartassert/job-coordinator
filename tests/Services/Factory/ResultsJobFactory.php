<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Repository\ResultsJobRepository;

readonly class ResultsJobFactory
{
    public function __construct(
        private ResultsJobRepository $resultsJobRepository,
    ) {
    }

    /**
     * @param null|non-empty-string $state
     * @param null|non-empty-string $endState
     */
    public function create(Job $job, ?string $state = null, ?string $endState = null): ResultsJob
    {
        $token = md5((string) rand());
        \assert('' !== $token);

        $state = is_string($state) ? $state : md5((string) rand());
        \assert('' !== $state);

        $resultsJob = new ResultsJob($job->id, $token, $state, $endState);

        $this->resultsJobRepository->save($resultsJob);

        return $resultsJob;
    }
}
