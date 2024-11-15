<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\MessageHandlingReadiness;
use App\Event\MessageNotHandleableEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Message\JobRemoteRequestMessageInterface;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
abstract readonly class AbstractMessageHandler
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
    ) {
    }

    protected function isReady(JobRemoteRequestMessageInterface $message): bool
    {
        $readiness = $this->readinessAssessor->isReady($message->getJobId());

        if (MessageHandlingReadiness::NEVER === $readiness) {
            $this->eventDispatcher->dispatch(new MessageNotHandleableEvent($message));

            return false;
        }

        if (MessageHandlingReadiness::EVENTUALLY === $readiness) {
            $this->isNotYetReady($message);

            return false;
        }

        return true;
    }

    protected function isNotYetReady(JobRemoteRequestMessageInterface $message): void
    {
        $this->eventDispatcher->dispatch(new MessageNotYetHandleableEvent($message));
    }
}
