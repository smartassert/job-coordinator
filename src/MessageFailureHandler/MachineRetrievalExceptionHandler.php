<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Exception\MachineRetrievalException;
use App\Services\RemoteRequestFailureRecorder;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;

class MachineRetrievalExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly RemoteRequestFailureRecorder $remoteRequestFailureRecorder,
    ) {
    }

    public function handle(\Throwable $throwable): void
    {
        if (!$throwable instanceof MachineRetrievalException) {
            return;
        }

        $this->remoteRequestFailureRecorder->record($throwable);
    }
}
