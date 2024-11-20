<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\JobCreatedEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Message\CreateResultsJobMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateResultsJobMessageDispatcher extends AbstractMessageDispatcher implements EventSubscriberInterface
{
    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobCreatedEvent::class => [
                ['dispatchForJobCreatedEvent', 100],
            ],
            MessageNotYetHandleableEvent::class => [
                ['redispatch', 100],
            ],
        ];
    }

    public function dispatchForJobCreatedEvent(JobCreatedEvent $event): void
    {
        if ($this->isNeverReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new CreateResultsJobMessage($event->getAuthenticationToken(), $event->getJobId())
        );
    }

    public function redispatch(MessageNotYetHandleableEvent $event): void
    {
        $message = $event->message;

        if (
            !$message instanceof CreateResultsJobMessage
            || MessageHandlingReadiness::NEVER === $this->readinessAssessor->isReady($message->getJobId())
        ) {
            return;
        }

        $this->messageDispatcher->dispatch($message);
    }
}
