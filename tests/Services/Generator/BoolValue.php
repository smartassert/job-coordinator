<?php

declare(strict_types=1);

namespace App\Tests\Services\Generator;

readonly class BoolValue
{
    public static function random(): bool
    {
        return (bool) rand(0, 1);
    }
}
