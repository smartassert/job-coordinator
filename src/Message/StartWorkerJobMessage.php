<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class StartWorkerJobMessage extends AbstractRemoteRequestMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        string $authenticationToken,
        string $jobId,
        public readonly string $machineIpAddress,
    ) {
        parent::__construct($authenticationToken, $jobId);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::MACHINE_START_JOB;
    }
}
