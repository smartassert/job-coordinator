<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;

class StartWorkerJobMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     * @param non-empty-string $machineIpAddress
     */
    public function __construct(
        string $authenticationToken,
        string $jobId,
        public readonly string $machineIpAddress,
    ) {
        parent::__construct($authenticationToken, $jobId);
    }

    public function getRemoteRequestEntity(): RemoteRequestEntity
    {
        return RemoteRequestEntity::WORKER_JOB;
    }

    public function getRemoteRequestAction(): RemoteRequestAction
    {
        return RemoteRequestAction::CREATE;
    }

    public function isRepeatable(): bool
    {
        return false;
    }
}
