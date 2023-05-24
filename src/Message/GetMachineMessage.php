<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;
use SmartAssert\WorkerManagerClient\Model\Machine;

class GetMachineMessage implements JobMessageInterface, RemoteRequestMessageInterface
{
    /**
     * @param non-empty-string $authenticationToken
     */
    public function __construct(
        public readonly string $authenticationToken,
        public readonly Machine $machine,
    ) {
    }

    public function getJobId(): string
    {
        return $this->machine->id;
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::MACHINE_GET;
    }
}
