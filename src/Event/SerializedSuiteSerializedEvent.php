<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class SerializedSuiteSerializedEvent extends Event
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $serializedSuiteId
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $jobId,
        public readonly string $serializedSuiteId,
    ) {
    }
}
