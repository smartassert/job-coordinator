<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\RemoteRequestType;

class CreateResultsJobMessage extends AbstractRemoteRequestMessage
{
    public function getRemoteRequestType(): RemoteRequestType
    {
        return RemoteRequestType::RESULTS_CREATE;
    }
}
