<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\Machine;
use App\Event\MachineCreationRequestedEvent;
use App\Repository\JobRepository;
use App\Repository\MachineRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineFactory implements EventSubscriberInterface
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
            MachineCreationRequestedEvent::class => [
                ['createOnMachineCreationRequestedEvent', 1000],
            ],
        ];
    }

    public function createOnMachineCreationRequestedEvent(MachineCreationRequestedEvent $event): void
    {
        $job = $this->jobRepository->find($event->getJobId());
        if (!$job instanceof Job) {
            return;
        }

        $machine = $event->machine;
        $machineEntity = $this->machineRepository->find($event->getJobId());

        if ($machineEntity instanceof Machine) {
            return;
        }

        $machine = new Machine($event->getJobId(), $machine->state, $machine->stateCategory, $machine->hasFailedState);
        $this->machineRepository->save($machine);
    }
}
