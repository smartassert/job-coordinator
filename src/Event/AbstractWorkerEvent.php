<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractWorkerEvent extends Event
{
    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $machineIpAddress
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $machineIpAddress,
    ) {
    }
}
