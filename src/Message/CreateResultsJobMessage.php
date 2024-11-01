<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Model\RemoteRequestType;

class CreateResultsJobMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    public function getRemoteRequestType(): RemoteRequestType
    {
        return new RemoteRequestType(RemoteRequestEntity::RESULTS_JOB, RemoteRequestAction::CREATE);
    }

    public function isRepeatable(): bool
    {
        return false;
    }
}
