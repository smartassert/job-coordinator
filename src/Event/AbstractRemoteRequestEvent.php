<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\RemoteRequest;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractRemoteRequestEvent extends Event implements RemoteRequestEventInterface
{
    public function __construct(
        private readonly RemoteRequest $remoteRequest,
    ) {
    }

    public function getRemoteRequest(): RemoteRequest
    {
        return $this->remoteRequest;
    }
}
