<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Model\RemoteRequestType;

class GetWorkerJobMessage extends AbstractRemoteRequestMessage
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
        return new RemoteRequestType(JobComponent::WORKER_JOB, RemoteRequestAction::RETRIEVE);
    }
}
