<?php

declare(strict_types=1);

namespace App\Message;

use App\Model\RemoteRequestType;

class TerminateMachineMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::createForMachineTermination();
    }
}
