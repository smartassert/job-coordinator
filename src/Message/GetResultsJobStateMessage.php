<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Model\RemoteRequestType;

class GetResultsJobStateMessage extends AbstractAuthenticatedRemoteRequestMessage
{
    public function getRemoteRequestType(): RemoteRequestType
    {
        return new RemoteRequestType(JobComponent::RESULTS_JOB, RemoteRequestAction::RETRIEVE);
    }
}
