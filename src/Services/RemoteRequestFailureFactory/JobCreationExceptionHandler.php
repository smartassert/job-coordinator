<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestFailureFactory;

use App\Enum\JobComponentName;
use App\Model\JobComponentErrorState;
use App\Model\RemoteRequestFailure;
use SmartAssert\WorkerClient\Model\JobCreationException;

class JobCreationExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(\Throwable $throwable): ?RemoteRequestFailure
    {
        if (!$throwable instanceof JobCreationException) {
            return null;
        }

        return RemoteRequestFailure::createForApplicationErrorState(
            new JobComponentErrorState(
                JobComponentName::WORKER_JOB,
                $throwable->errorState,
            ),
        );
    }
}
