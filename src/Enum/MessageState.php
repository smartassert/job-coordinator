<?php

declare(strict_types=1);

namespace App\Enum;

enum MessageState: string
{
    case HANDLING = 'handling';
    case HALTED = 'halted';
    case STOPPED = 'stopped';
}
