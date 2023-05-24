<?php

declare(strict_types=1);

namespace App\Message;

class StartWorkerJobMessage implements JobMessageInterface
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        public readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly string $machineIpAddress,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
