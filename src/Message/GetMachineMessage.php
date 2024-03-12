<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;
use SmartAssert\WorkerManagerClient\Model\MachineInterface;

class GetMachineMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    /**
     * @param non-empty-string $authenticationToken
     * @param non-empty-string $jobId
     */
    public function __construct(
        string $authenticationToken,
        string $jobId,
        public readonly MachineInterface $machine,
    ) {
        parent::__construct($authenticationToken, $jobId);
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::MACHINE_GET;
    }
}
