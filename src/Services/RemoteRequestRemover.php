<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequestFailure;
use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;

class RemoteRequestRemover
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly RemoteRequestFailureRepository $remoteRequestFailureRepository,
    ) {
    }

    public function removeForJobAndType(string $jobId, RemoteRequestEntity $entity, RemoteRequestAction $action): void
    {
        $job = $this->jobRepository->find($jobId);
        if (null === $job) {
            return;
        }

        $remoteRequests = $this->remoteRequestRepository->findBy([
            'jobId' => $job->id,
            'entity' => $entity,
            'action' => $action,
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
