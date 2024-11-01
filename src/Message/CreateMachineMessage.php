<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;

class CreateMachineMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    public function getRemoteRequestEntity(): RemoteRequestEntity
    {
        return RemoteRequestEntity::MACHINE;
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
