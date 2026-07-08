<?php

declare(strict_types=1);

namespace App\Event;

use SmartAssert\SourcesClient\Model\SerializedSuite;
use Symfony\Contracts\EventDispatcher\Event;

class SerializedSuiteRetrievedEvent extends Event implements JobEventInterface
{
    use GetJobIdTrait;

    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        private readonly string $jobId,
        public readonly SerializedSuite $serializedSuite,
    ) {}
}
