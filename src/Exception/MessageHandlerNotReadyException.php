<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\MessageHandlingReadiness;
use App\Message\JobRemoteRequestMessageInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;

class MessageHandlerNotReadyException extends \Exception implements UnrecoverableExceptionInterface
{
    public function __construct(
        private readonly JobRemoteRequestMessageInterface $handlerMessage,
        private readonly MessageHandlingReadiness $readiness,
    ) {
        parent::__construct(
            sprintf(
                'Failed to %s %s for job "%s": %s handleable',
                $handlerMessage->getRemoteRequestType()->action->value,
                $handlerMessage->getRemoteRequestType()->jobComponent->value,
                $handlerMessage->getJobId(),
                MessageHandlingReadiness::NEVER === $this->readiness ? 'never' : 'not yet'
            ),
        );
    }

    public function getJobId(): string
    {
        return $this->message->getJobId();
    }

    public function getHandlerMessage(): JobRemoteRequestMessageInterface
    {
        return $this->handlerMessage;
    }

    public function getReadiness(): MessageHandlingReadiness
    {
        return $this->readiness;
    }
}
