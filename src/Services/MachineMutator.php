<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Machine;
use App\Entity\MachineActionFailure;
use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineStateChangeEvent;
use App\Repository\MachineRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineMutator implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly MachineRepository $machineRepository,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineStateChangeEvent::class => [
                ['setStateOnMachineStateChangeEvent', 1000],
            ],
            MachineIsActiveEvent::class => [
                ['setIpOnMachineIsActiveEvent', 1000],
            ],
            MachineHasActionFailureEvent::class => [
                ['setActionFailureOnMachineHasActionFailureEvent', 1000],
            ],
        ];
    }

    public function setStateOnMachineStateChangeEvent(MachineStateChangeEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $machineEntity = $this->machineRepository->find($job->getId());
        if (!$machineEntity instanceof Machine) {
            return;
        }

        $machineEntity->setState($event->getMachine()->state);
        $machineEntity->setStateCategory($event->getMachine()->stateCategory);
        $machineEntity->setHasFailedState($event->getMachine()->hasFailedState);
        $machineEntity->setHasEndState($event->getMachine()->hasEndState);

        $this->machineRepository->save($machineEntity);
    }

    public function setIpOnMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $machineEntity = $this->machineRepository->find($job->getId());
        if (!$machineEntity instanceof Machine) {
            return;
        }

        $machineEntity->setIp($event->ipAddress);

        $this->machineRepository->save($machineEntity);
    }

    public function setActionFailureOnMachineHasActionFailureEvent(MachineHasActionFailureEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $machine = $event->getMachine();
        if (null === $machine->actionFailure) {
            return;
        }

        $machineEntity = $this->machineRepository->find($job->getId());
        if (!$machineEntity instanceof Machine) {
            return;
        }

        if ($machineEntity->getActionFailure() instanceof MachineActionFailure) {
            return;
        }

        $machineEntity->setActionFailure(
            new MachineActionFailure(
                $job->getId(),
                $machine->actionFailure->action,
                $machine->actionFailure->type,
                $machine->actionFailure->context,
            )
        );
    }
}
