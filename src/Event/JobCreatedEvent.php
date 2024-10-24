<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class JobCreatedEvent extends Event implements JobEventInterface, AuthenticatingEventInterface
{
    /**
     * @param non-empty-string                          $authenticationToken
     * @param non-empty-string                          $jobId
     * @param array<non-empty-string, non-empty-string> $parameters
     */
    public function __construct(
        private readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly array $parameters,
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
