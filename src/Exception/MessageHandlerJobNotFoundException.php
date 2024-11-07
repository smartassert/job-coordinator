<?php

declare(strict_types=1);

namespace App\Exception;

use App\Exception\MessageHandlerTargetEntityNotFoundException as BaseException;
use App\Exception\UnhandleableMessageExceptionInterface as UnhandleableMessage;
use App\Message\JobRemoteRequestMessageInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface as Unrecoverable;

class MessageHandlerJobNotFoundException extends BaseException implements Unrecoverable, UnhandleableMessage
{
    public function __construct(JobRemoteRequestMessageInterface $failedMessage)
    {
        parent::__construct($failedMessage, 'Job');
    }

    public function getFailedMessage(): JobRemoteRequestMessageInterface
    {
        return $this->failedMessage;
    }
}
