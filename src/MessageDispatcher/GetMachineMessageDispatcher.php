<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetMachineMessageDispatcher extends AbstractMessageDispatcher implements EventSubscriberInterface
{
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
        if ($this->isNeverReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatch(new GetMachineMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $event->getMachine()
        ));
    }

    public function dispatch(MachineCreationRequestedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new GetMachineMessage($event->getAuthenticationToken(), $event->getJobId(), $event->getMachine())
        );
    }
}
