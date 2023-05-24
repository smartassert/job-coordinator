<?php

declare(strict_types=1);

namespace App\Message;

class CreateResultsJobMessage implements JobMessageInterface
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        public readonly string $authenticationToken,
        private readonly string $jobId,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
