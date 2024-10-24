<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Contracts\EventDispatcher\Event;

class ResultsJobStateRetrievedEvent extends Event implements JobEventInterface
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        public readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly ResultsJobState $resultsJobState,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
