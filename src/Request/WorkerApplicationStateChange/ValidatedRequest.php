<?php

declare(strict_types=1);

namespace App\Request\WorkerApplicationStateChange;

final readonly class ValidatedRequest
{
    /**
     * @param non-empty-string       $jobId
     * @param non-empty-array<mixed> $stateData
     */
    public function __construct(
        public string $jobId,
        public array $stateData,
    ) {}
}
