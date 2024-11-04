<?php

declare(strict_types=1);

namespace App\Repository;

interface JobComponentRepositoryInterface
{
    /**
     * @param array<string, mixed> $criteria
     */
    public function count(array $criteria = []): int;
}
