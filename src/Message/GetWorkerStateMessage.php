<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class GetWorkerStateMessage extends AbstractRemoteRequestMessage
{
    /**
     * @param non-empty-string $jobId
     * @param non-empty-string $machineIpAddress
     */
    public function __construct(
        string $jobId,
        public readonly string $machineIpAddress
    ) {
        parent::__construct($jobId);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::MACHINE_STATE_GET;
    }

    public function isRepeatable(): bool
    {
        return true;
    }
}
