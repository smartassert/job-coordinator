<?php

declare(strict_types=1);

namespace App\Model;

interface SerializeToArrayInterface
{
    /**
     * @return array<mixed>
     */
    public function toArray(): array;
}
