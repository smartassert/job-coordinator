<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class GetResultsJobStateMessage extends AbstractRemoteRequestMessage
{
    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::RESULTS_CREATE;
    }
}
