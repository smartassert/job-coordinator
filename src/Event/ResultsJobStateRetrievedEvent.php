<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\ResultsClient\Model\JobState as ResultsJobState;
use Symfony\Contracts\EventDispatcher\Event;

class ResultsJobStateRetrievedEvent extends Event implements JobEventInterface, AuthenticatingEventInterface
{
    use GetJobIdTrait;
    use GetAuthenticationTokenTrait;

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        private readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly ResultsJobState $resultsJobState,
    ) {}
}
