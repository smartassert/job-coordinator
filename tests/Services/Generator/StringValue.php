<?php

declare(strict_types=1);

namespace App\Tests\Services\Generator;

readonly class StringValue
{
    /**
     * @return non-empty-string
     */
    public static function random(): string
    {
        return bin2hex(random_bytes(16));
    }
}
