<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Entity\SerializedSuite;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Repository\SerializedSuiteRepository;

readonly class SerializedSuiteFactory
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
    ) {}

    public function createPreparedForJob(JobInterface $job): SerializedSuite
    {
        $serializedSuiteId = md5((string) rand());
        $state = md5((string) rand());

        $resultsJob = new SerializedSuite(
            $job->getId(),
            $serializedSuiteId,
            $state,
            true,
            true,
            new MetaState(true, true),
        );

        $this->serializedSuiteRepository->save($resultsJob);

        return $resultsJob;
    }

    public function createNewForJob(JobInterface $job): SerializedSuite
    {
        $serializedSuiteId = md5((string) rand());
        $state = md5((string) rand());

        $resultsJob = new SerializedSuite(
            $job->getId(),
            $serializedSuiteId,
            $state,
            false,
            false,
            new MetaState(false, false),
        );

        $this->serializedSuiteRepository->save($resultsJob);

        return $resultsJob;
    }
}
