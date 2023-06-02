<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Entity\RemoteRequest;
use App\Enum\RequestState;
use App\Exception\SerializedSuiteCreationException;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestFailureFactory\RemoteRequestFailureFactory;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;

class SerializedSuiteCreationExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly RemoteRequestFailureFactory $remoteRequestFailureFactory,
    ) {
    }

    public function handle(\Throwable $throwable): void
    {
        if (!$throwable instanceof SerializedSuiteCreationException) {
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

        $throwable->job->setSerializedSuiteRequestState(RequestState::FAILED);
        $this->jobRepository->add($throwable->job);
    }
}
