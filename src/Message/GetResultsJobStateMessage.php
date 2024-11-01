<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Model\RemoteRequestType;

class GetResultsJobStateMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    public function getRemoteRequestType(): RemoteRequestType
    {
        return new RemoteRequestType(RemoteRequestEntity::RESULTS_JOB, RemoteRequestAction::RETRIEVE);
    }

    public function isRepeatable(): bool
    {
        return true;
    }
}
