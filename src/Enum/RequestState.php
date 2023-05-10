<?php

declare(strict_types=1);

namespace App\Enum;

enum RequestState: string
{
    case UNKNOWN = 'unknown';
    case REQUESTING = 'requesting';
    case HALTED = 'halted';
    case FAILED = 'failed';
    case SUCCEEDED = 'succeeded';
}