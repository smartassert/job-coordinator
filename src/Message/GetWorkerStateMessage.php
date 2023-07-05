<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class GetWorkerStateMessage extends AbstractRemoteRequestMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        string $authenticationToken,
        string $jobId,
    ) {
        parent::__construct($authenticationToken, $jobId);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::MACHINE_STATE_GET;
    }
}
