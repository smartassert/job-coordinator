<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class MachineTerminationRequestedEvent extends Event implements JobEventInterface
{
    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        private readonly string $jobId,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
