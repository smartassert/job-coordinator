<?php

declare(strict_types=1);

namespace App\Message;

use App\Model\RemoteRequestType;
use SmartAssert\WorkerManagerClient\Model\Machine;

class GetMachineMessage extends AbstractRemoteRequestMessage
{
    /**
     * @param non-empty-string $jobId
     */
    public function __construct(
        string $jobId,
        public readonly Machine $machine,
    ) {
        parent::__construct($jobId);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::createForMachineRetrieval();
    }
}
