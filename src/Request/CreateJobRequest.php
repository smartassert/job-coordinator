<?php

namespace App\Request;

class CreateJobRequest
{
    /**
     * @param non-empty-string   $suiteId
     * @param non-empty-string[] $manifestPaths
     */
    public function __construct(
        public readonly string $suiteId,
        public readonly array $manifestPaths,
    ) {
    }
}
