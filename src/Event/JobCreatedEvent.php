<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class JobCreatedEvent extends Event implements JobEventInterface, AuthenticatingEventInterface
{
    use GetJobIdTrait;
    use GetAuthenticationTokenTrait;

    /**
     * @param non-empty-string                          $authenticationToken
     * @param non-empty-string                          $jobId
     * @param non-empty-string                          $suiteId
     * @param array<non-empty-string, non-empty-string> $parameters
     */
    public function __construct(
        private readonly string $authenticationToken,
        private readonly string $jobId,
        private readonly string $suiteId,
        public readonly array $parameters,
    ) {}

    /**
     * @return non-empty-string
     */
    public function getSuiteId(): string
    {
        return $this->suiteId;
    }
}
