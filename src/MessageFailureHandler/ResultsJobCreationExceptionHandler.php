<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Enum\RequestState;
use App\Exception\ResultsJobCreationException;
use App\Repository\JobRepository;
use App\Services\RemoteRequestFailureRecorder;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;

class ResultsJobCreationExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly RemoteRequestFailureRecorder $remoteRequestFailureRecorder,
    ) {
    }

    public function handle(\Throwable $throwable): void
    {
        if (!$throwable instanceof ResultsJobCreationException) {
            return;
        }

        $this->remoteRequestFailureRecorder->record($throwable);

        $throwable->getJob()->setResultsJobRequestState(RequestState::FAILED);
        $this->jobRepository->add($throwable->getJob());
    }
}
