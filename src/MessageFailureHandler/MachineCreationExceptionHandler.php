<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Exception\MachineCreationException;
use App\Services\RemoteRequestFailureRecorder;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;

class MachineCreationExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly RemoteRequestFailureRecorder $remoteRequestFailureRecorder,
    ) {
    }

    public function handle(\Throwable $throwable): void
    {
        if (!$throwable instanceof MachineCreationException) {
            return;
        }

        $this->remoteRequestFailureRecorder->record($throwable);
    }
}
