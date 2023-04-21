<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Entity\Job;
use App\Event\MachineIsActiveEvent;
use App\Message\StartWorkerJobMessage;
use App\Repository\JobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class StartWorkerJobMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineIsActiveEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    public function dispatch(MachineIsActiveEvent $event): void
    {
        $machine = $event->current;

        $job = $this->jobRepository->find($machine->id);
        if (!$job instanceof Job) {
            return;
        }

        $this->messageBus->dispatch(new StartWorkerJobMessage($event->authenticationToken, $machine));
    }
}
