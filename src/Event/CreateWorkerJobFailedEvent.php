<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class CreateWorkerJobFailedEvent extends Event implements JobEventInterface
{
    use GetJobIdTrait;

    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        private readonly string $jobId,
    ) {}
}
