<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Entity\Job;
use App\Entity\SerializedSuite;
use App\Repository\SerializedSuiteRepository;

readonly class SerializedSuiteFactory
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
    ) {
    }

    public function createPreparedForJob(Job $job): SerializedSuite
    {
        $serializedSuiteId = md5((string) rand());
        \assert('' !== $serializedSuiteId);

        $state = md5((string) rand());
        \assert('' !== $state);

        $resultsJob = new SerializedSuite($job->id, $serializedSuiteId, $state, true, true);

        $this->serializedSuiteRepository->save($resultsJob);

        return $resultsJob;
    }
}
