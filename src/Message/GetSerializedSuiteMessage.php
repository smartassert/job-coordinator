<?php

declare(strict_types=1);

namespace App\Message;

class GetSerializedSuiteMessage implements JobMessageInterface
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteId
     */
    public function __construct(
        public readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly string $serializedSuiteId,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
