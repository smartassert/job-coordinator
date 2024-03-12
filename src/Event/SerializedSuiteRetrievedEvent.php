<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\SourcesClient\Model\SerializedSuiteInterface;
use Symfony\Contracts\EventDispatcher\Event;

class SerializedSuiteRetrievedEvent extends Event
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $jobId,
        public readonly SerializedSuiteInterface $serializedSuite,
    ) {
    }
}
