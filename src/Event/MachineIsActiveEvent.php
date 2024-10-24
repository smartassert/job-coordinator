<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class MachineIsActiveEvent extends Event implements JobEventInterface, AuthenticatingEventInterface
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $ipAddress
     */
    public function __construct(
        private readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly string $ipAddress,
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
