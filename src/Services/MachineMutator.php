<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\Machine;
use App\Entity\MachineActionFailure;
use App\Event\MachineHasActionFailureEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineStateChangeEvent;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineMutator implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
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
        $job = $this->jobRepository->find($event->getJobId());
        if (!$job instanceof Job) {
            return;
        }

        $machineEntity = $this->machineRepository->find($event->getJobId());
        if (!$machineEntity instanceof Machine) {
            return;
        }

        $machineEntity->setState($event->current->state);
        $machineEntity->setStateCategory($event->current->stateCategory);
        $machineEntity->setHasFailedState($event->current->hasFailedState);

        $this->machineRepository->save($machineEntity);
    }

    public function setIpOnMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $job = $this->jobRepository->find($event->getJobId());
        if (!$job instanceof Job) {
            return;
        }

        $machineEntity = $this->machineRepository->find($job->id);
        if (!$machineEntity instanceof Machine) {
            return;
        }

        $machineEntity->setIp($event->ipAddress);

        $this->machineRepository->save($machineEntity);
    }

    public function setActionFailureOnMachineHasActionFailureEvent(MachineHasActionFailureEvent $event): void
    {
        $job = $this->jobRepository->find($event->getJobId());
        if (!$job instanceof Job) {
            return;
        }

        $machineEntity = $this->machineRepository->find($job->id);
        if (!$machineEntity instanceof Machine) {
            return;
        }

        if ($machineEntity->getActionFailure() instanceof MachineActionFailure) {
            return;
        }

        $machineEntity->setActionFailure(
            new MachineActionFailure(
                $job->id,
                $event->machineActionFailure->action,
                $event->machineActionFailure->type,
                $event->machineActionFailure->context,
            )
        );
    }
}
