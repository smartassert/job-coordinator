<?php

declare(strict_types=1);

namespace App\Exception;

use App\Message\JobRemoteRequestMessageInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

class MessageHandlerJobNotFoundException extends MessageHandlerTargetEntityNotFoundException implements UnrecoverableExceptionInterface
{
    public function __construct(JobRemoteRequestMessageInterface $handledMessage) {
        parent::__construct($handledMessage, 'Job');
    }
}
