<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestStateTracker;

use App\Message\JobRemoteRequestMessageInterface;

interface EventHandlerInterface
{
    public function handle(object $event, JobRemoteRequestMessageInterface $message): bool;
}
