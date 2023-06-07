<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\RemoteRequestType;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestRepository;

class RemoteRequestRemover
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    public function removeForJobAndType(string $jobId, RemoteRequestType $type): void
    {
        $job = $this->jobRepository->find($jobId);
        if (null === $job) {
            return;
        }

        $remoteRequests = $this->remoteRequestRepository->findBy([
            'jobId' => $job->id,
            'type' => $type,
        ]);

        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->remove($remoteRequest);
        }
    }
}
