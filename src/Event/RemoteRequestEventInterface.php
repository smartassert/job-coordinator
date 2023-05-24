<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\RemoteRequest;

interface RemoteRequestEventInterface
{
    public function getRemoteRequest(): RemoteRequest;
}
