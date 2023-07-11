<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineIsActiveEvent;
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
        ];
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new StartWorkerJobMessage($event->authenticationToken, $event->jobId, $event->ipAddress)
        );
    }
}
