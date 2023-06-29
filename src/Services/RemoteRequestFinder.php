<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Enum\RemoteRequestType;
use App\Model\PendingRemoteRequest;
use App\Model\RemoteRequestInterface;
use App\Repository\RemoteRequestRepository;

class RemoteRequestFinder
{
    public function __construct(
        private readonly RemoteRequestRepository $repository,
        private readonly RemoteRequestType $type,
    ) {
    }

    public function findNewest(Job $job): RemoteRequestInterface
    {
        $resultsJobRequest = $this->repository->findOneBy(
            [
                'jobId' => $job->id,
                'type' => $this->type,
            ],
            [
                'index' => 'DESC',
            ]
        );

        return null === $resultsJobRequest ? new PendingRemoteRequest() : $resultsJobRequest;
    }
}
