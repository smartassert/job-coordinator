<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Model\JobInterface;
use App\Repository\ResultsJobRepository;

abstract class AbstractCreateJobSuccessTest extends AbstractCreateJobSuccessSetup
{
    public function testJobResponseData(): void
    {
        $job = $this->getJob();

        self::assertSame($job?->getId(), self::$createResponseData['id']);
        self::assertSame($job?->getSuiteId(), self::$createResponseData['suite_id']);
        self::assertSame(
            $job?->getMaximumDurationInSeconds(),
            self::$createResponseData['maximum_duration_in_seconds']
        );
    }

    public function testJobIsCreated(): void
    {
        self::assertInstanceOf(JobInterface::class, $this->getJob());
    }

    public function testJobUser(): void
    {
        self::assertSame($this->getJob()?->getUserId(), self::$user['id']);
    }

    public function testJobResultsTokenIsNotSet(): void
    {
        $resultsJobRepository = self::getContainer()->get(ResultsJobRepository::class);
        \assert($resultsJobRepository instanceof ResultsJobRepository);

        self::assertNull($resultsJobRepository->find(self::$createResponseData['id'] ?? null));
    }
}
