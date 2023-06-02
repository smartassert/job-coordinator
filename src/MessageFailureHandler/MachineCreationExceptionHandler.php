<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Entity\RemoteRequest;
use App\Enum\RequestState;
use App\Exception\MachineCreationException;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestFailureFactory\RemoteRequestFailureFactory;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;

class MachineCreationExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly RemoteRequestFailureFactory $remoteRequestFailureFactory,
    ) {
    }

    public function handle(\Throwable $throwable): void
    {
        if (!$throwable instanceof MachineCreationException) {
            return;
        }

        $remoteRequest = $this->remoteRequestRepository->findOneBy([
            'jobId' => $throwable->job->id,
        ]);

        if ($remoteRequest instanceof RemoteRequest) {
            $remoteRequestFailure = $this->remoteRequestFailureFactory->create($throwable->previousException);

            if (null !== $remoteRequestFailure) {
                $remoteRequest->setFailure($remoteRequestFailure);
                $this->remoteRequestRepository->save($remoteRequest);
            }
        }

        $throwable->job->setMachineRequestState(RequestState::FAILED);
        $this->jobRepository->add($throwable->job);
    }
}
