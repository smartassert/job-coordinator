<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Machine;
use App\Entity\MachineActionFailure;
use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineIsReadyEvent;
use App\Event\MachineStateChangeEvent;
use App\Model\MetaState;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineMutator implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly MachineRepository $machineRepository,
    ) {}

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
                ['handleMachineIsActiveEvent', 1000],
            ],
            MachineHasActionFailureEvent::class => [
                ['setActionFailureOnMachineHasActionFailureEvent', 1000],
            ],
            MachineIsReadyEvent::class => [
                ['handleMachineIsReadyEvent', 1000],
            ],
        ];
    }

    public function setStateOnMachineStateChangeEvent(MachineStateChangeEvent $event): void
    {
        $job = $this->jobRepository->findOneBy(['id' => $event->getJobId()]);
        if (null === $job) {
            return;
        }

        $machineEntity = $this->machineRepository->find($job->getId());
        if (!$machineEntity instanceof Machine) {
            return;
        }

        $machineEntity->setState($event->getMachine()->state);
        $machineEntity->setStateCategory($event->getMachine()->stateCategory);
        $machineEntity->setMetaState(
            new MetaState(
                $event->getMachine()->metaState->ended,
                $event->getMachine()->metaState->succeeded,
                $event->getMachine()->metaState->pending,
            )
        );

        $this->machineRepository->save($machineEntity);
    }

    public function handleMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $job = $this->jobRepository->findOneBy(['id' => $event->getJobId()]);
        if (null === $job) {
            return;
        }

        $machineEntity = $this->machineRepository->find($job->getId());
        if (!$machineEntity instanceof Machine) {
            return;
        }

        $machineEntity->setIp($event->ipAddress);
        $machineEntity->setIsActive();

        $this->machineRepository->save($machineEntity);
    }

    public function handleMachineIsReadyEvent(MachineIsReadyEvent $event): void
    {
        $job = $this->jobRepository->findOneBy(['id' => $event->getJobId()]);
        if (null === $job) {
            return;
        }

        $machineEntity = $this->machineRepository->find($job->getId());
        if (!$machineEntity instanceof Machine) {
            return;
        }

        $machineEntity->setIsReady();

        $this->machineRepository->save($machineEntity);
    }

    public function setActionFailureOnMachineHasActionFailureEvent(MachineHasActionFailureEvent $event): void
    {
        $job = $this->jobRepository->findOneBy(['id' => $event->getJobId()]);
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
