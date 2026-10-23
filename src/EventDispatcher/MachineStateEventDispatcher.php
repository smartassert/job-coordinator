<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Model\Machine\ActionFailureInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineStateEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineRetrievedEvent::class => [
                ['dispatchMachineStateChangeEvent', 100],
                ['dispatchMachineIsActiveEvent', 100],
                ['dispatchMachineHasActionFailureEvent', 100],
            ],
        ];
    }

    public function dispatchMachineStateChangeEvent(MachineRetrievedEvent $event): void
    {
        if ($event->previous->getState() !== $event->getMachine()->getState()) {
            $this->eventDispatcher->dispatch(new MachineStateChangeEvent($event->previous, $event->getMachine()));
        }
    }

    public function dispatchMachineIsActiveEvent(MachineRetrievedEvent $event): void
    {
        $machineWasPreviouslyNotYetActive
            = !$event->previous->isActive()
            && !$event->previous->getMetaState()->hasFailedState()
            && !$event->previous->isEnding()
            && !$event->previous->getMetaState()->hasEndState();

        $machineIsNowActive = $event->getMachine()->isActive();

        if ($machineWasPreviouslyNotYetActive && $machineIsNowActive) {
            $primaryIpAddress = $event->getMachine()->getIpAddress();
            if (null === $primaryIpAddress || '' === $primaryIpAddress) {
                return;
            }

            $this->eventDispatcher->dispatch(new MachineIsActiveEvent(
                $primaryIpAddress,
                $event->getMachine(),
            ));
        }
    }

    public function dispatchMachineHasActionFailureEvent(MachineRetrievedEvent $event): void
    {
        if (
            null === $event->previous->getActionFailure()
            && $event->getMachine()->getActionFailure() instanceof ActionFailureInterface
        ) {
            $this->eventDispatcher->dispatch(
                new MachineHasActionFailureEvent($event->getMachine())
            );
        }
    }
}
