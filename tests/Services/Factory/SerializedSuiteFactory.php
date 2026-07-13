<?php

declare(strict_types=1);

namespace App\Tests\Services\Factory;

use App\Entity\SerializedSuite;
use App\Model\JobInterface;
use App\Model\MetaState;
use App\Repository\SerializedSuiteRepository;
use App\Tests\Services\Generator\Id;
use App\Tests\Services\Generator\StringValue;

readonly class SerializedSuiteFactory
{
    public function __construct(
        private SerializedSuiteRepository $serializedSuiteRepository,
    ) {}

    /**
     * @param ?non-empty-string $state
     */
    public function createNewForJob(JobInterface $job, ?string $state = null): SerializedSuite
    {
        $serializedSuiteId = Id::generate();
        $state = null === $state ? StringValue::random() : $state;

        $serializedSuite = new SerializedSuite(
            $job->getId(),
            $serializedSuiteId,
            $state,
            new MetaState(false, false, true),
        );

        $this->serializedSuiteRepository->save($serializedSuite);

        return $serializedSuite;
    }
}
