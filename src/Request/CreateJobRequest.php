<?php

namespace App\Request;

class CreateJobRequest
{
    /**
     * @param non-empty-string $suiteId
     */
    public function __construct(
        public readonly string $suiteId,
    ) {
    }
}
