<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Model\RemoteRequestType;

class CreateMachineMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    public function getRemoteRequestType(): RemoteRequestType
    {
        return new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::CREATE);
    }
}
