<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\RemoteRequestType;

interface TypedRemoteRequestInterface
{
    public function getType(): RemoteRequestType;
}
