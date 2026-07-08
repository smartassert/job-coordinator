<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use Symfony\Contracts\EventDispatcher\Event;

class ResultsJobRetrievedEvent extends Event implements JobEventInterface
{
    use GetJobIdTrait;

    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        private readonly string $jobId,
        public readonly ResultsJob $resultsJob,
    ) {}
}
