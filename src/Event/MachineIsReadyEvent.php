<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class MachineIsReadyEvent extends Event implements JobEventInterface
{
    use GetJobIdTrait;

    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $ipAddress
     */
    public function __construct(
        private readonly string $jobId,
        public readonly string $ipAddress,
    ) {}
}
