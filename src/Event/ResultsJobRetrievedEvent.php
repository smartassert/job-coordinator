<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use Symfony\Contracts\EventDispatcher\Event;

class ResultsJobRetrievedEvent extends Event implements JobEventInterface
{
    public function __construct(
        public readonly ResultsJob $resultsJob,
    ) {}

    public function getJobId(): string
    {
        return $this->resultsJob->label;
    }
}
