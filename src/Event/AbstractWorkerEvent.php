<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractWorkerEvent extends Event implements JobEventInterface
{
    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $machineIpAddress
     */
    public function __construct(
        private readonly string $jobId,
        public readonly string $machineIpAddress,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
