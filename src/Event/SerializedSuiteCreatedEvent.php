<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\SourcesClient\Model\SerializedSuite;
use Symfony\Contracts\EventDispatcher\Event;

class SerializedSuiteCreatedEvent extends Event implements JobEventInterface
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        public readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly SerializedSuite $serializedSuite,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
