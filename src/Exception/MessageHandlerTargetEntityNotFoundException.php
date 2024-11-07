<?php

declare(strict_types=1);

namespace App\Exception;

use App\Exception\UnhandleableMessageExceptionInterface as UnhandleableMessage;
use App\Message\JobRemoteRequestMessageInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface as Unrecoverable;

class MessageHandlerTargetEntityNotFoundException extends \Exception implements Unrecoverable, UnhandleableMessage
{
    public function __construct(
        public readonly JobRemoteRequestMessageInterface $failedMessage,
        public readonly string $targetEntity,
    ) {
        parent::__construct(
            sprintf(
                'Failed to %s %s for job "%s": %s entity not found',
                $failedMessage->getRemoteRequestType()->action->value,
                $failedMessage->getRemoteRequestType()->jobComponent->value,
                $failedMessage->getJobId(),
                $targetEntity,
            )
        );
    }

    public function getFailedMessage(): JobRemoteRequestMessageInterface
    {
        return $this->failedMessage;
    }
}
