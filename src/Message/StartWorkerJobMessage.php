<?php

declare(strict_types=1);

namespace App\Message;

class StartWorkerJobMessage
{
    /**
     * @param non-empty-string $authenticationToken
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $jobId,
        public readonly string $machineIpAddress,
    ) {
    }
}
