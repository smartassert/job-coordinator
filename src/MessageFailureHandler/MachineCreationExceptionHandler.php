<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Enum\RequestState;
use App\Exception\MachineCreationException;
use App\Repository\JobRepository;
use App\Services\RemoteRequestFailureRecorder;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;

class MachineCreationExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly RemoteRequestFailureRecorder $remoteRequestFailureRecorder,
    ) {
    }

    public function handle(\Throwable $throwable): void
    {
        if (!$throwable instanceof MachineCreationException) {
            return;
        }

        $this->remoteRequestFailureRecorder->record($throwable);

        $throwable->getJob()->setMachineRequestState(RequestState::FAILED);
        $this->jobRepository->add($throwable->getJob());
    }
}
