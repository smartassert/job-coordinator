<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Repository\ResultsJobRepository;
use App\Tests\Services\Factory\JobFactory;
use App\Tests\Services\Factory\ResultsJobFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class ResultsJobRepositoryTest extends WebTestCase
{
    private ResultsJobRepository $resultsJobRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);
        $this->resultsJobRepository = $resultsJobRepository;
    }

    public function testHasDoesNotHave(): void
    {
        self::assertFalse($this->resultsJobRepository->has((string) new Ulid()));
    }

    public function testHasDoesHave(): void
    {
        $jobFactory = self::getContainer()->get(JobFactory::class);
        \assert($jobFactory instanceof JobFactory);
        $job = $jobFactory->createRandom();

        $resultsJobFactory = self::getContainer()->get(ResultsJobFactory::class);
        \assert($resultsJobFactory instanceof ResultsJobFactory);
        $resultsJob = $resultsJobFactory->create($job);

        $this->resultsJobRepository->save($resultsJob);

        self::assertTrue($this->resultsJobRepository->has($job->id));
    }
}
