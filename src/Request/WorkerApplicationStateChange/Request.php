<?php

declare(strict_types=1);

namespace App\Request\WorkerApplicationStateChange;

class Request
{
    public const string KEY_JOB = 'job';
    public const string KEY_STATE_DATA = 'state';

    /**
     * @param ?non-empty-string $jobId
     * @param array<mixed>      $stateData
     */
    public function __construct(
        public readonly ?string $jobId,
        public readonly array $stateData,
    ) {}
}
