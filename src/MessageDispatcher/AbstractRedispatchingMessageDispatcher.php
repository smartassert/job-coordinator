<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MessageNotYetHandleableEvent;
use App\Message\JobRemoteRequestMessageInterface;

abstract readonly class AbstractRedispatchingMessageDispatcher extends AbstractMessageDispatcher
{
    public function redispatch(MessageNotYetHandleableEvent $event): void
    {
        $message = $event->message;

        if (
            !$this->handles($message)
            || MessageHandlingReadiness::NEVER === $this->readinessAssessor->isReady($message->getJobId())
        ) {
            return;
        }

        $this->messageDispatcher->dispatch($message);
    }

    abstract protected function handles(JobRemoteRequestMessageInterface $message): bool;
}
