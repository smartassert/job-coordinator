<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use SmartAssert\WorkerManagerClient\Model\ActionFailure;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineStateEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

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
        if ($event->previous->state !== $event->getMachine()->state) {
            $this->eventDispatcher->dispatch(new MachineStateChangeEvent($event->previous, $event->getMachine()));
        }
    }

    public function dispatchMachineIsActiveEvent(MachineRetrievedEvent $event): void
    {
        $machineWasPreviouslyNotYetActive =
            !$event->previous->hasActiveState
            && !$event->previous->hasFailedState
            && !$event->previous->hasEndingState
            && !$event->previous->hasEndState;

        $machineIsNowActive = $event->getMachine()->hasActiveState;

        if ($machineWasPreviouslyNotYetActive && $machineIsNowActive) {
            $primaryIpAddress = $event->getMachine()->ipAddresses[0] ?? null;
            if (!is_string($primaryIpAddress)) {
                return;
            }

            $this->eventDispatcher->dispatch(new MachineIsActiveEvent(
                $event->getAuthenticationToken(),
                $event->getJobId(),
                $primaryIpAddress,
                $event->getMachine(),
            ));
        }
    }

    public function dispatchMachineHasActionFailureEvent(MachineRetrievedEvent $event): void
    {
        if (
            null === $event->previous->actionFailure
            && $event->getMachine()->actionFailure instanceof ActionFailure
        ) {
            $this->eventDispatcher->dispatch(
                new MachineHasActionFailureEvent($event->getJobId(), $event->getMachine())
            );
        }
    }
}
