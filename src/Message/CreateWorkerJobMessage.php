<?php

declare(strict_types=1);

namespace App\Message;

use App\Model\RemoteRequestType;

class CreateWorkerJobMessage extends AbstractRemoteRequestMessage
{
    /**
     * @param non-empty-string $jobId
     * @param positive-int     $maximumDurationInSeconds
     * @param non-empty-string $machineIpAddress
     */
    public function __construct(
        string $jobId,
        public readonly int $maximumDurationInSeconds,
        public readonly string $machineIpAddress,
    ) {
        parent::__construct($jobId);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::createForWorkerJobCreation();
    }
}
