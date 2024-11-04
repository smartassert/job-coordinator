<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Model\RemoteRequestType;
use SmartAssert\WorkerManagerClient\Model\Machine;

class GetMachineMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        string $authenticationToken,
        string $jobId,
        public readonly Machine $machine,
    ) {
        parent::__construct($authenticationToken, $jobId);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return new RemoteRequestType(RemoteRequestEntity::MACHINE, RemoteRequestAction::RETRIEVE);
    }
}
