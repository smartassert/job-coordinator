<?php

declare(strict_types=1);

namespace App\Message;

abstract class AbstractRemoteRequestMessage implements JobRemoteRequestMessageInterface
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
