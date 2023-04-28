<?php

declare(strict_types=1);

namespace App\Request;

class CreateJobRequest
{
    /**
     * @param non-empty-string                          $suiteId
     * @param array<non-empty-string, non-empty-string> $parameters
     */
    public function __construct(
        public readonly string $suiteId,
        public readonly array $parameters,
    ) {
    }
}
