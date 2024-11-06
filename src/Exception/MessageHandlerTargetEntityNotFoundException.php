<?php

declare(strict_types=1);

namespace App\Exception;

use App\Message\JobRemoteRequestMessageInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

class MessageHandlerTargetEntityNotFoundException extends \Exception implements UnrecoverableExceptionInterface
{
    public function __construct(
        public readonly JobRemoteRequestMessageInterface $handledMessage,
        public readonly string $targetEntity,
    ) {
        parent::__construct(
            sprintf(
                'Failed to %s %s for job "%s": %s entity not found',
                $handledMessage->getRemoteRequestType()->action->value,
                $handledMessage->getRemoteRequestType()->jobComponent->value,
                $handledMessage->getJobId(),
                $targetEntity,
            )
        );
    }
}
