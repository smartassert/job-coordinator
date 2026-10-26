<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Model\Machine\ActionFailure;
use App\Repository\MachineRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineStateEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MachineRepository $machineRepository,
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
        $previousMachineEntity = $this->machineRepository->find($event->getMachine()->getId());
        if (null === $previousMachineEntity) {
            return;
        }

        if ($previousMachineEntity->getState() !== $event->getMachine()->getState()) {
            $this->eventDispatcher->dispatch(new MachineStateChangeEvent($event->getMachine()));
        }
    }

    public function dispatchMachineIsActiveEvent(MachineRetrievedEvent $event): void
    {
        $previousEntity = $this->machineRepository->find($event->getJobId());
        if (null === $previousEntity) {
            return;
        }

        $machineWasPreviouslyNotYetActive
            = !$previousEntity->isActive()
            && !$previousEntity->getMetaState()->hasFailedState()
            && !$previousEntity->isEnding()
            && !$previousEntity->getMetaState()->hasEndState();

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
        $previousEntity = $this->machineRepository->find($event->getJobId());
        if (null === $previousEntity) {
            return;
        }

        if (
            null === $previousEntity->getActionFailure()
            && $event->getMachine()->getActionFailure() instanceof ActionFailure
        ) {
            $this->eventDispatcher->dispatch(
                new MachineHasActionFailureEvent($event->getMachine())
            );
        }
    }
}
