<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class CreateMachineMessage extends AbstractRemoteRequestMessage
{
    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::MACHINE_CREATE;
    }
}
