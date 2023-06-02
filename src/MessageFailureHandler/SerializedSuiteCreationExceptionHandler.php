<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Enum\RequestState;
use App\Exception\SerializedSuiteCreationException;
use App\Repository\JobRepository;
use App\Services\RemoteRequestFailureRecorder;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;

class SerializedSuiteCreationExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly RemoteRequestFailureRecorder $remoteRequestFailureRecorder,
    ) {
    }

    public function handle(\Throwable $throwable): void
    {
        if (!$throwable instanceof SerializedSuiteCreationException) {
            return;
        }

        $this->remoteRequestFailureRecorder->record($throwable);

        $throwable->getJob()->setSerializedSuiteRequestState(RequestState::FAILED);
        $this->jobRepository->add($throwable->getJob());
    }
}
