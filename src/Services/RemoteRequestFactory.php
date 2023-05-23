<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequest;
use App\Enum\RemoteRequestType;
use App\Exception\EmptyUlidException;
use App\Repository\RemoteRequestRepository;

class RemoteRequestFactory
{
    public function __construct(
        private readonly RemoteRequestRepository $repository,
        private readonly UlidFactory $ulidFactory,
    ) {
    }

    /**
     * @param non-empty-string $jobId
     *
     * @throws EmptyUlidException
     */
    public function create(string $jobId, RemoteRequestType $type): RemoteRequest
    {
        $remoteRequest = $this->repository->findOneBy([
            'jobId' => $jobId,
            'type' => $type,
        ]);

        if (null === $remoteRequest) {
            $remoteRequest = new RemoteRequest($this->ulidFactory->create(), $jobId, $type);
            $this->repository->save($remoteRequest);
        }

        return $remoteRequest;
    }
}
