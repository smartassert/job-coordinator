<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Event\MachineIsActiveEvent;
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
            MachineIsActiveEvent::class => [
                ['setMachineIpAddressOnMachineIsActiveEvent', 1000],
            ],
        ];
    }

    public function setMachineIpAddressOnMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $machine = $event->current;

        $job = $this->jobRepository->find($machine->id);
        if (!$job instanceof Job || null !== $job->getMachineIpAddress()) {
            return;
        }

        $job->setMachineIpAddress($event->ipAddress);
        $this->jobRepository->add($job);
    }
}
