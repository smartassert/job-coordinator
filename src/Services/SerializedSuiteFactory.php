<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\SerializedSuite;
use App\Repository\SerializedSuiteRepository;

class SerializedSuiteFactory
{
    public function __construct(
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
    ) {
    }

    /**
     * @param non-empty-string $serializedSuiteId
     * @param non-empty-string $state
     */
    public function create(Job $job, string $serializedSuiteId, string $state): SerializedSuite
    {
        $serializedSuite = $this->serializedSuiteRepository->find($job->id);
        if (null === $serializedSuite) {
            $serializedSuite = new SerializedSuite($job->id, $serializedSuiteId, $state);
            $this->serializedSuiteRepository->save($serializedSuite);
        }

        return $serializedSuite;
    }
}
