<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobCreatedEvent;
use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Message\CreateSerializedSuiteMessage;
use App\Messenger\NonDelayedStamp;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateSerializedSuiteMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobCreatedEvent::class => [
                ['dispatchForJobCreatedEvent', 100],
            ],
        ];
    }

    public function dispatchForJobCreatedEvent(JobCreatedEvent $event): void
    {
        $message = new CreateSerializedSuiteMessage($event->authenticationToken, $event->jobId, 0, $event->parameters);

        $this->eventDispatcher->dispatch(new JobRemoteRequestMessageCreatedEvent($message));
        $this->messageBus->dispatch(new Envelope($message, [new NonDelayedStamp()]));
    }
}
