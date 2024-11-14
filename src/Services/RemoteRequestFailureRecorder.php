<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequest;
use App\Exception\RemoteRequestExceptionInterface;
use App\Repository\RemoteRequestRepository;
use App\Services\RemoteRequestFailureFactory\RemoteRequestFailureFactory;

class RemoteRequestFailureRecorder
{
    public function __construct(
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly RemoteRequestFailureFactory $remoteRequestFailureFactory,
    ) {
    }

    public function record(RemoteRequestExceptionInterface $throwable): void
    {
        $remoteRequest = $this->remoteRequestRepository->findOneBy([
            'jobId' => $throwable->getJobId(),
            'type' => $throwable->getFailedMessage()->getRemoteRequestType(),
            'index' => $throwable->getFailedMessage()->getIndex(),
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
