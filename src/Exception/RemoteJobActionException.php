<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;
use App\Message\JobRemoteRequestMessageInterface;
use App\Model\RemoteRequestType;

class RemoteJobActionException extends \Exception implements RemoteRequestExceptionInterface
{
    public function __construct(
        private readonly Job $job,
        private readonly \Throwable $previousException,
        private readonly JobRemoteRequestMessageInterface $failedMessage,
        private readonly RemoteRequestType $remoteRequestType,
    ) {
        parent::__construct(
            sprintf(
                'Failed to %s %s for job "%s": %s',
                $remoteRequestType->action->value,
                $remoteRequestType->entity->value,
                $job->id,
                $previousException->getMessage()
            ),
            0,
            $previousException
        );
    }

    public function getJob(): Job
    {
        return $this->job;
    }

    public function getPreviousException(): \Throwable
    {
        return $this->previousException;
    }

    public function getFailedMessage(): JobRemoteRequestMessageInterface
    {
        return $this->failedMessage;
    }

    public function getRemoteRequestType(): RemoteRequestType
    {
        return $this->remoteRequestType;
    }
}
