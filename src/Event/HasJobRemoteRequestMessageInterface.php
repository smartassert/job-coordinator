<?php

declare(strict_types=1);

namespace App\Event;

use App\Message\JobRemoteRequestMessageInterface;

interface HasJobRemoteRequestMessageInterface
{
    public function getMessage(): JobRemoteRequestMessageInterface;
}
