<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class TerminateMachineMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::MACHINE_TERMINATE;
    }
}
