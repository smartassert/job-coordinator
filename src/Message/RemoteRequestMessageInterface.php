<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

interface RemoteRequestMessageInterface
{
    public function getRemoteRequestType(): RemoteRequestType;
}
