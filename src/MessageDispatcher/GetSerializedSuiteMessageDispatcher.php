<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Message\GetSerializedSuiteMessage;
use App\Messenger\NonDelayedStamp;
use App\Model\SerializedSuiteEndStates;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class GetSerializedSuiteMessageDispatcher implements EventSubscriberInterface
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
            SerializedSuiteCreatedEvent::class => [
                ['dispatchForSerializedSuiteCreatedEvent', 100],
            ],
            SerializedSuiteRetrievedEvent::class => [
                ['dispatchForSerializedSuiteRetrievedEvent', 100],
            ],
        ];
    }

    public function dispatchForSerializedSuiteCreatedEvent(SerializedSuiteCreatedEvent $event): void
    {
        $message = new GetSerializedSuiteMessage(
            $event->authenticationToken,
            $event->jobId,
            0,
            $event->serializedSuite->getId()
        );

        $this->eventDispatcher->dispatch(new JobRemoteRequestMessageCreatedEvent($message));
        $this->messageBus->dispatch(new Envelope($message, [new NonDelayedStamp()]));
    }

    public function dispatchForSerializedSuiteRetrievedEvent(SerializedSuiteRetrievedEvent $event): void
    {
        $serializedSuiteState = $event->serializedSuite->getState();

        if (in_array($serializedSuiteState, SerializedSuiteEndStates::END_STATES)) {
            return;
        }

        $message = new GetSerializedSuiteMessage(
            $event->authenticationToken,
            $event->jobId,
            0,
            $event->serializedSuite->getId(),
        );

        $this->eventDispatcher->dispatch(new JobRemoteRequestMessageCreatedEvent($message));
        $this->messageBus->dispatch($message);
    }
}
