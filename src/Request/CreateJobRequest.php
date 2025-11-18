<?php

declare(strict_types=1);

namespace App\Request;

class CreateJobRequest
{
    public const KEY_MAXIMUM_DURATION_IN_SECONDS = 'maximum_duration_in_seconds';
    public const KEY_PARAMETERS = 'parameters';
    public const MAXIMUM_DURATION_IN_SECONDS_MAX_SIZE = 2147483647;

    /**
     * @param non-empty-string                          $suiteId
     * @param positive-int                              $maximumDurationInSeconds
     * @param array<non-empty-string, non-empty-string> $parameters
     */
    public function __construct(
        public readonly string $suiteId,
        public int $maximumDurationInSeconds,
        public readonly array $parameters,
    ) {}
}
