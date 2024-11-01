<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Repository\RemoteRequestRepository;

class RemoteRequestIndexGenerator
{
    public function __construct(
        private readonly RemoteRequestRepository $remoteRequestRepository,
    ) {
    }

    /**
     * @return int<0, max>
     */
    public function generate(string $jobId, RemoteRequestEntity $entity, RemoteRequestAction $action): int
    {
        $largestIndex = $this->remoteRequestRepository->getLargestIndex($jobId, $entity, $action);

        return null === $largestIndex ? 0 : $largestIndex + 1;
    }
}
