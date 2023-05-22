<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\SerializedSuiteCreatedEvent;
use App\Message\GetSerializedSuiteMessage;
use App\Messenger\NonDelayedStamp;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class GetSerializedSuiteStateMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
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
        ];
    }

    public function dispatchForSerializedSuiteCreatedEvent(SerializedSuiteCreatedEvent $event): void
    {
        $this->messageBus->dispatch(new Envelope(
            new GetSerializedSuiteMessage($event->authenticationToken, $event->serializedSuite->getId()),
            [new NonDelayedStamp()]
        ));
    }
}
