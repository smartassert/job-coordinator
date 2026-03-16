<?php

declare(strict_types=1);

namespace App\Enum;

enum MessageState
{
    case HANDLING;
    case HALTED;
    case STOPPED;
}
