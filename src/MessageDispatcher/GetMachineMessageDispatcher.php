<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GetMachineMessageDispatcher implements EventSubscriberInterface
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
            MachineCreationRequestedEvent::class => [
                ['dispatch', 100],
            ],
            MachineRetrievedEvent::class => [
                ['dispatchIfMachineNotInEndState', 100],
            ],
        ];
    }

    public function dispatchIfMachineNotInEndState(MachineRetrievedEvent $event): void
    {
        if ('end' === $event->current->stateCategory) {
            return;
        }

        $this->messageDispatcher->dispatch(new GetMachineMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $event->current
        ));
    }

    public function dispatch(MachineCreationRequestedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new GetMachineMessage($event->getAuthenticationToken(), $event->getJobId(), $event->machine)
        );
    }
}
