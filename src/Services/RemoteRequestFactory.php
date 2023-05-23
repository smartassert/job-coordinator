<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
use App\Repository\RemoteRequestRepository;

class RemoteRequestFactory
{
    public function __construct(
        private readonly RemoteRequestRepository $repository,
    ) {
    }

    /**
     * @param non-empty-string $jobId
     */
    public function create(string $jobId, RemoteRequestType $type): RemoteRequest
    {
        $remoteRequest = $this->repository->find(RemoteRequest::generateId($jobId, $type));

        if (null === $remoteRequest) {
            $remoteRequest = new RemoteRequest($jobId, $type);
            $this->repository->save($remoteRequest);
        }

        return $remoteRequest;
    }
}
