<?php

declare(strict_types=1);

namespace App\Message;

class CreateSerializedSuiteMessage
{
    /**
     * @param non-empty-string                          $authenticationToken
     * @param non-empty-string                          $jobId
     * @param array<non-empty-string, non-empty-string> $parameters
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $jobId,
        public readonly array $parameters,
    ) {
    }
}
