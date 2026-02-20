<?php

declare(strict_types=1);

namespace App\Enum;

enum PreparationState: string
{
    case PENDING = 'pending';
    case PREPARING = 'preparing';
    case FAILED = 'failed';
    case SUCCEEDED = 'succeeded';

    public static function isEndState(PreparationState $state): bool
    {
        return self::FAILED === $state || self::SUCCEEDED === $state;
    }

    public static function isSuccessState(PreparationState $state): bool
    {
        return self::SUCCEEDED === $state;
    }
}
