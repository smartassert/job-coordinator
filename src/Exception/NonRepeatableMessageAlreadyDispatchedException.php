<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;
use App\Entity\RemoteRequest;

class NonRepeatableMessageAlreadyDispatchedException extends \Exception
{
    public function __construct(
        public readonly Job $job,
        public readonly RemoteRequest $existingRemoteRequest,
    ) {
        parent::__construct(sprintf(
            'Unable to to repeat request of type "%s" for job "%s", existing request "%s" already sent',
            $this->existingRemoteRequest->getType(),
            $job->id,
            $existingRemoteRequest->id,
        ));
    }
}
