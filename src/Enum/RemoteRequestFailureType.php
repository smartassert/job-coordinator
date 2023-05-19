<?php

declare(strict_types=1);

namespace App\Enum;

enum RemoteRequestFailureType: string
{
    case HTTP = 'http';
    case NETWORK = 'network';
}
