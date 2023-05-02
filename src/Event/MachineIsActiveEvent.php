<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class MachineIsActiveEvent extends Event
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $ipAddress
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $jobId,
        public readonly string $ipAddress,
    ) {
    }
}
