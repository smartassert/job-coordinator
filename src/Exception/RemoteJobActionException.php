<?php

declare(strict_types=1);

namespace App\Exception;

use App\Message\JobRemoteRequestMessageInterface;
use App\Model\JobInterface;

class RemoteJobActionException extends \Exception implements RemoteRequestExceptionInterface
{
    public function __construct(
        private readonly JobInterface $job,
        private readonly \Throwable $previousException,
        private readonly JobRemoteRequestMessageInterface $failedMessage,
    ) {
        parent::__construct(
            sprintf(
                'Failed to %s %s for job "%s": %s',
                $failedMessage->getRemoteRequestType()->action->value,
                $failedMessage->getRemoteRequestType()->jobComponent->value,
                $job->getId(),
                $previousException->getMessage()
            ),
            0,
            $previousException
        );
    }

    public function getJob(): JobInterface
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
}
