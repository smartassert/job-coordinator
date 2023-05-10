<?php

declare(strict_types=1);

namespace App\Message;

class CreateWorkerMachineMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly string $jobId,
    ) {
    }
}
