<?php

declare(strict_types=1);

namespace App\Exception;

use App\Exception\MessageHandlerTargetEntityNotFoundException as BaseException;
use App\Message\JobRemoteRequestMessageInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

class MessageHandlerJobNotFoundException extends BaseException implements UnrecoverableExceptionInterface
{
    public function __construct(JobRemoteRequestMessageInterface $handledMessage)
    {
        parent::__construct($handledMessage, 'Job');
    }
}
