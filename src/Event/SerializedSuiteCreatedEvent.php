<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\SourcesClient\Model\SerializedSuite;
use Symfony\Contracts\EventDispatcher\Event;

class SerializedSuiteCreatedEvent extends Event implements JobEventInterface, AuthenticatingEventInterface
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        private readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly SerializedSuite $serializedSuite,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getAuthenticationToken(): string
    {
        return $this->authenticationToken;
    }
}
