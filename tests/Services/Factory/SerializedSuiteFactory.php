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

    public function createNewForJob(JobInterface $job): SerializedSuite
    {
        $serializedSuiteId = md5((string) rand());
        $state = md5((string) rand());

        $serializedSuite = new SerializedSuite(
            $job->getId(),
            $serializedSuiteId,
            $state,
            new MetaState(false, false),
        );

        $this->serializedSuiteRepository->save($serializedSuite);

        return $serializedSuite;
    }
}
