<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;

class GetResultsJobStateMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    public function getRemoteRequestEntity(): RemoteRequestEntity
    {
        return RemoteRequestEntity::RESULTS_JOB;
    }

    public function getRemoteRequestAction(): RemoteRequestAction
    {
        return RemoteRequestAction::RETRIEVE;
    }

    public function isRepeatable(): bool
    {
        return true;
    }
}
