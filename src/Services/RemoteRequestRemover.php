<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequestFailure;
use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;

class RemoteRequestRemover
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly RemoteRequestFailureRepository $remoteRequestFailureRepository,
    ) {}

    public function removeForJobAndType(string $jobId, RemoteRequestType $type): void
    {
        $job = $this->jobStore->retrieve($jobId);
        if (null === $job) {
            return;
        }

        $remoteRequests = $this->remoteRequestRepository->findBy([
            'jobId' => $job->getId(),
            'type' => $type,
        ]);

        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->remove($remoteRequest);

            $remoteRequestFailure = $remoteRequest->getFailure();
            if ($remoteRequestFailure instanceof RemoteRequestFailure) {
                $this->remoteRequestFailureRepository->remove($remoteRequestFailure);
            }
        }
    }
}
