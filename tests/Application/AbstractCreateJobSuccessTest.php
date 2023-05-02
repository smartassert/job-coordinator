<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Entity\Job;
use App\Repository\JobRepository;

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

    public function testJobResultsTokenIsSet(): void
    {
        $job = $this->getJob();
        \assert($job instanceof Job);

        self::assertNotNull($job->resultsToken);
    }

    public function testJobResponseData(): void
    {
        $job = $this->getJob();
        \assert($job instanceof Job);

        self::assertSame(
            [
                'job' => [
                    'id' => $job->id,
                    'suite_id' => $job->suiteId,
                    'serialized_suite_id' => $job->serializedSuiteId,
                    'maximum_duration_in_seconds' => $job->maximumDurationInSeconds,
                ],
                'machine' => [
                    'id' => $job->id,
                    'state' => 'create/received',
                    'state_category' => 'pre_active',
                    'ip_addresses' => [],
                ],
            ],
            self::$createResponseData,
        );
    }
}
