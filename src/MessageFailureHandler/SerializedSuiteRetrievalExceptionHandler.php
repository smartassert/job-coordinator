<?php

declare(strict_types=1);

namespace App\MessageFailureHandler;

use App\Entity\RemoteRequest;
use App\Exception\SerializedSuiteRetrievalException;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestFailureFactory\RemoteRequestFailureFactory;
use SmartAssert\WorkerMessageFailedEventBundle\ExceptionHandlerInterface;

class SerializedSuiteRetrievalExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly RemoteRequestFailureFactory $remoteRequestFailureFactory,
    ) {
    }

    public function handle(\Throwable $throwable): void
    {
        if (!$throwable instanceof SerializedSuiteRetrievalException) {
            return;
        }

        $remoteRequest = $this->remoteRequestRepository->findOneBy([
            'jobId' => $throwable->getJob()->id,
        ]);

        if ($remoteRequest instanceof RemoteRequest) {
            $remoteRequestFailure = $this->remoteRequestFailureFactory->create($throwable->getPreviousException());

            if (null !== $remoteRequestFailure) {
                $remoteRequest->setFailure($remoteRequestFailure);
                $this->remoteRequestRepository->save($remoteRequest);
            }
        }
    }
}
