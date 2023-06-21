<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\Machine;
use App\Event\MachineRetrievedEvent;
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
            MachineRetrievedEvent::class => [
                ['createOnMachineRetrievedEvent', 1000],
            ],
        ];
    }

    public function createOnMachineRetrievedEvent(MachineRetrievedEvent $event): void
    {
        $machine = $event->current;

        $job = $this->jobRepository->find($machine->id);
        if (!$job instanceof Job) {
            return;
        }

        $this->create($job, $machine->state, $machine->stateCategory);
    }

    /**
     * @param non-empty-string $state
     * @param non-empty-string $stateCategory
     */
    public function create(Job $job, string $state, string $stateCategory): Machine
    {
        $machine = $this->machineRepository->find($job->id);
        if (null === $machine) {
            $machine = new Machine($job->id, $state, $stateCategory);
            $this->machineRepository->save($machine);
        }

        return $machine;
    }
}
