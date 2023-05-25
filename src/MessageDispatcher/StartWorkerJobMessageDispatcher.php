<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Event\MachineIsActiveEvent;
use App\Message\StartWorkerJobMessage;
use App\Messenger\NonDelayedStamp;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class StartWorkerJobMessageDispatcher implements EventSubscriberInterface
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
            MachineIsActiveEvent::class => [
                ['dispatchForMachineIsActiveEvent', 100],
            ],
        ];
    }

    public function dispatchForMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $message = new StartWorkerJobMessage($event->authenticationToken, $event->jobId, 0, $event->ipAddress);

        $this->eventDispatcher->dispatch(new JobRemoteRequestMessageCreatedEvent($message));
        $this->messageBus->dispatch(new Envelope($message, [new NonDelayedStamp()]));
    }
}
