<?php

declare(strict_types=1);

namespace App\Exception;

use App\Message\JobRemoteRequestMessageInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

class MessageHandlerJobNotFoundException extends \Exception implements UnrecoverableExceptionInterface
{
    public function __construct(
        public readonly JobRemoteRequestMessageInterface $handledMessage,
    ) {
        parent::__construct(
            sprintf(
                'Failed to %s %s for job "%s": Job not found',
                $handledMessage->getRemoteRequestType()->action->value,
                $handledMessage->getRemoteRequestType()->entity->value,
                $handledMessage->getJobId(),
            )
        );
    }
}
