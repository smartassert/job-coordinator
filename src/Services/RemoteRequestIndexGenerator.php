<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\RemoteRequestType;
use App\Repository\RemoteRequestRepository;

class RemoteRequestIndexGenerator
{
    public function __construct(
        private readonly RemoteRequestRepository $remoteRequestRepository,
    ) {}

    /**
     * @return int<0, max>
     */
    public function generate(string $jobId, RemoteRequestType $type): int
    {
        $largestIndex = $this->remoteRequestRepository->getLargestIndex($jobId, $type);

        return null === $largestIndex ? 0 : $largestIndex + 1;
    }
}
