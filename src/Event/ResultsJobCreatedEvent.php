<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\ResultsClient\Model\Job as ResultsJob;
use Symfony\Contracts\EventDispatcher\Event;

class ResultsJobCreatedEvent extends Event
{
    public function __construct(
        public readonly ResultsJob $resultsJob,
    ) {
    }
}
