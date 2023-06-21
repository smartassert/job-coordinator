<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;

abstract class AbstractCreateJobSuccessTest extends AbstractCreateJobSuccessSetup
{
    public function testJobIsCreated(): void
    {
        $jobRepository = self::getContainer()->get(JobRepository::class);
        \assert($jobRepository instanceof JobRepository);
        self::assertCount(1, $jobRepository->findAll());

        $jobs = $jobRepository->findAll();
        $job = $jobs[0] ?? null;
        self::assertInstanceOf(Job::class, $job);
    }

    public function testJobUser(): void
    {
        $job = $this->getJob();
        \assert($job instanceof Job);

        self::assertSame($job->userId, self::$user->id);
    }

    public function testJobResultsTokenIsNotSet(): void
    {
        $job = $this->getJob();
        \assert($job instanceof Job);

        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        self::assertNull($resultsJobRepository->find($job->id));
    }

    public function testJobResponseData(): void
    {
        $job = $this->getJob();
        \assert($job instanceof Job);

        self::assertSame(
            [
                'id' => $job->id,
                'suite_id' => $job->suiteId,
                'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
            ],
            self::$createResponseData,
        );
    }
}
