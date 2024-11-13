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
        \assert('' !== $job->getId());

        $serializedSuiteId = md5((string) rand());
        $state = md5((string) rand());

        $resultsJob = new SerializedSuite($job->getId(), $serializedSuiteId, $state, true, true);

        $this->serializedSuiteRepository->save($resultsJob);

        return $resultsJob;
    }
}
