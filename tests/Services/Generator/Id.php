<?php

declare(strict_types=1);

namespace App\Tests\Services\Generator;

use Symfony\Component\Uid\Ulid;

readonly class Id
{
    /**
     * @return non-empty-string
     */
    public static function generate(): string
    {
        return (string) new Ulid();
    }
}
