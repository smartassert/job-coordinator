<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineIsActiveEvent;
use App\Event\NotReadyToStartWorkerJobEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
use App\Message\StartWorkerJobMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StartWorkerJobMessageDispatcher implements EventSubscriberInterface
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
            MachineIsActiveEvent::class => [
                ['dispatchForMachineIsActiveEvent', 100],
            ],
            NotReadyToStartWorkerJobEvent::class => [
                ['dispatchForNotReadyToStartWorkerJobEvent', 100],
            ],
        ];
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new StartWorkerJobMessage($event->getAuthenticationToken(), $event->getJobId(), $event->ipAddress)
        );
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForNotReadyToStartWorkerJobEvent(NotReadyToStartWorkerJobEvent $event): void
    {
        $this->messageDispatcher->dispatch($event->message);
    }
}
