<?php

declare(strict_types=1);

namespace App\Enum;

enum RemoteRequestAction: string
{
    case CREATE = 'create';
    case RETRIEVE = 'retrieve';
    case TERMINATE = 'terminate';
}
