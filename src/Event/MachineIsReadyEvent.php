<?php

declare(strict_types=1);

namespace App\Event;

use App\Event\AuthenticatingEventInterface as AuthenticatingEvent;
use Symfony\Contracts\EventDispatcher\Event;

class MachineIsReadyEvent extends Event implements JobEventInterface, AuthenticatingEvent
{
    use GetJobIdTrait;
    use GetAuthenticationTokenTrait;

    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $ipAddress
     */
    public function __construct(
        private readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly string $ipAddress,
    ) {}
}
