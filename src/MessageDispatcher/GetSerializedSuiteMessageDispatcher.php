<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
use App\Message\GetSerializedSuiteMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GetSerializedSuiteMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRemoteRequestMessageDispatcher $messageDispatcher,
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

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForSerializedSuiteCreatedEvent(SerializedSuiteCreatedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(new GetSerializedSuiteMessage(
            $event->authenticationToken,
            $event->getJobId(),
            $event->serializedSuite->getId()
        ));
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForSerializedSuiteRetrievedEvent(SerializedSuiteRetrievedEvent $event): void
    {
        $this->messageDispatcher->dispatch(new GetSerializedSuiteMessage(
            $event->authenticationToken,
            $event->getJobId(),
            $event->serializedSuite->getId(),
        ));
    }
}
