<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineStateChangeEvent;
use App\Repository\JobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JobMutator implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineStateChangeEvent::class => [
                ['setMachineStateCategoryOnMachineStateChangeEvent', 1000],
            ],
            MachineCreationRequestedEvent::class => [
                ['setMachineOnMachineCreationRequestedEvent', 1000],
            ],
        ];
    }

    public function setMachineStateCategoryOnMachineStateChangeEvent(MachineStateChangeEvent $event): void
    {
        $machine = $event->current;

        $job = $this->jobRepository->find($machine->id);
        if (!$job instanceof Job) {
            return;
        }

        $job->setMachineStateCategory($machine->stateCategory);
        $this->jobRepository->add($job);
    }

    public function setMachineOnMachineCreationRequestedEvent(MachineCreationRequestedEvent $event): void
    {
        $machine = $event->machine;
        $jobId = $machine->id;

        $job = $this->jobRepository->find($jobId);
        if (!$job instanceof Job) {
            return;
        }

        if ('' !== $machine->stateCategory) {
            $job = $job->setMachineStateCategory($machine->stateCategory);
        }

        $this->jobRepository->add($job);
    }
}
