<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobCreatedEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Message\CreateResultsJobMessage;
use App\Message\JobRemoteRequestMessageInterface;
use App\MessageDispatcher\AbstractRedispatchingMessageDispatcher as BaseMessageDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateResultsJobMessageDispatcher extends BaseMessageDispatcher implements EventSubscriberInterface
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

    protected function handles(JobRemoteRequestMessageInterface $message): bool
    {
        return $message instanceof CreateResultsJobMessage;
    }
}
