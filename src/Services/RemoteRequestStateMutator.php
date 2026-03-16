<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequest;
use App\Enum\RequestState;
use App\Message\JobRemoteRequestMessageInterface;
use App\Repository\RemoteRequestRepository;

readonly class RemoteRequestStateMutator
{
    public function __construct(
        private RemoteRequestRepository $remoteRequestRepository,
    ) {}

    public function setRemoteRequestStateForMessage(
        JobRemoteRequestMessageInterface $message,
        RequestState $requestState
    ): void {
        $jobId = $message->getJobId();

        $remoteRequest = $this->remoteRequestRepository->find(
            RemoteRequest::generateId($jobId, $message->getRemoteRequestType(), $message->getIndex())
        );

        if ($remoteRequest instanceof RemoteRequest) {
            if ($requestState === $remoteRequest->getState()) {
                return;
            }
        } else {
            $remoteRequest = new RemoteRequest($jobId, $message->getRemoteRequestType(), $message->getIndex());
        }

        $remoteRequest->setState($requestState);
        $this->remoteRequestRepository->save($remoteRequest);
    }
}
