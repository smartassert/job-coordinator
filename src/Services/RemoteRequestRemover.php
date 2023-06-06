<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequest;
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

    /**
     * @return RemoteRequest[]
     */
    public function removeForJobAndType(string $jobId, RemoteRequestType $type): array
    {
        $job = $this->jobRepository->find($jobId);
        if (null === $job) {
            return [];
        }

        $remoteRequests = $this->remoteRequestRepository->findBy([
            'jobId' => $job->id,
            'type' => $type,
        ]);

        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->remove($remoteRequest);
        }

        return $remoteRequests;
    }
}
