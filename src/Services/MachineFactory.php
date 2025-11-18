<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Machine;
use App\Event\MachineCreationRequestedEvent;
use App\Repository\MachineRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineFactory implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly MachineRepository $machineRepository,
    ) {}

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
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $machine = $event->getMachine();
        $machineEntity = $this->machineRepository->find($job->getId());

        if ($machineEntity instanceof Machine) {
            return;
        }

        $entity = new Machine(
            $job->getId(),
            $machine->state,
            $machine->stateCategory,
            $machine->hasFailedState,
            $machine->hasEndState,
        );
        $this->machineRepository->save($entity);
    }
}
