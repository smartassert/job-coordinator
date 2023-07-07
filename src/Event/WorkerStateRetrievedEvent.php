<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\WorkerClient\Model\ApplicationState;
use Symfony\Contracts\EventDispatcher\Event;

class WorkerStateRetrievedEvent extends Event
{
    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        public readonly string $jobId,
        public readonly ApplicationState $state,
    ) {
    }
}
