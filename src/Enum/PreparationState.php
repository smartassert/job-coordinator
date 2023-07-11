<?php

declare(strict_types=1);

namespace App\Enum;

enum PreparationState: string
{
    case PENDING = 'pending';
    case PREPARING = 'preparing';
    case FAILED = 'failed';
    case SUCCEEDED = 'succeeded';
}
