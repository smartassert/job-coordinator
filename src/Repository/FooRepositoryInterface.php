<?php

declare(strict_types=1);

namespace App\Repository;

interface FooRepositoryInterface
{
    /**
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int;
}
