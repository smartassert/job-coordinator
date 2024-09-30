<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Job;
use App\Message\JobRemoteRequestMessageInterface;

abstract class AbstractRemoteRequestException extends \Exception implements RemoteRequestExceptionInterface
{
    public function __construct(
        private readonly Job $job,
        private readonly \Throwable $previousException,
        string $message,
        private readonly JobRemoteRequestMessageInterface $failedMessage,
    ) {
        parent::__construct($message, 0, $previousException);
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
}
