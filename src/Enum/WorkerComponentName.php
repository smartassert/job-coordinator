<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkerComponentName: string
{
    case APPLICATION = 'application';
    case COMPILATION = 'compilation';
    case EXECUTION = 'execution';
    case EVENT_DELIVERY = 'event_delivery';
}
