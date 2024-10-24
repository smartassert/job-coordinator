<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class SerializedSuiteSerializedEvent extends Event implements JobEventInterface
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
