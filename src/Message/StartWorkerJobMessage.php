<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class StartWorkerJobMessage implements JobMessageInterface, RemoteRequestMessageInterface
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        public readonly string $authenticationToken,
        private readonly string $jobId,
        public readonly string $machineIpAddress,
    ) {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::MACHINE_START_JOB;
    }
}
