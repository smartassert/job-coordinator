<?php

declare(strict_types=1);

namespace App\Exception;

use App\Message\JobRemoteRequestMessageInterface;

class RemoteJobActionException extends \Exception implements RemoteRequestExceptionInterface
{
    public function __construct(
        private readonly \Throwable $previousException,
        private readonly JobRemoteRequestMessageInterface $failedMessage,
    ) {
        parent::__construct(
            sprintf(
                'Failed to %s %s for job "%s": %s',
                $failedMessage->getRemoteRequestType()->action->value,
                $failedMessage->getRemoteRequestType()->jobComponent->value,
                $this->failedMessage->getJobId(),
                $previousException->getMessage()
            ),
            0,
            $previousException
        );
    }

    public function getJobId(): string
    {
        return $this->failedMessage->getJobId();
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
