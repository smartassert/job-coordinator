<?php

declare(strict_types=1);

namespace App\Tests\Services\Generator;

readonly class Ip
{
    /**
     * @return non-empty-string
     */
    public static function random(): string
    {
        return rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255) . '.' . rand(0, 255);
    }
}
