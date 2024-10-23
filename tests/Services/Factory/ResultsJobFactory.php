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

    public function createRandomForJob(Job $job): ResultsJob
    {
        $token = md5((string) rand());
        \assert('' !== $token);

        $state = md5((string) rand());
        \assert('' !== $state);

        $resultsJob = new ResultsJob($job->id, $token, $state, null);

        $this->resultsJobRepository->save($resultsJob);

        return $resultsJob;
    }
}
