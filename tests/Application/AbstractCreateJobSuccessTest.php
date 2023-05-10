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

    public function testJobResultsTokenIsNotSet(): void
    {
        $job = $this->getJob();
        \assert($job instanceof Job);

        self::assertNull($job->resultsToken);
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
                'serialized_suite' => [
                    'id' => $job->getSerializedSuiteId(),
                    'state' => $job->getSerializedSuiteState(),
                    'request_state' => $job->getSerializedSuiteRequestState()->value,
                ],
                'machine' => [
                    'state_category' => null,
                    'ip_address' => null,
                ],
                'results_job' => [
                    'request_state' => $job->getResultsJobRequestState()->value,
                ],
            ],
            self::$createResponseData,
        );
    }
}
