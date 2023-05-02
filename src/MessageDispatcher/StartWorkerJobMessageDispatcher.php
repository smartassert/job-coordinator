<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Entity\Job;
use App\Event\MachineIsActiveEvent;
use App\Message\StartWorkerJobMessage;
use App\Repository\JobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class StartWorkerJobMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly int $dispatchDelay,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineIsActiveEvent::class => [
                ['dispatchForMachineIsActiveEvent', 100],
            ],
        ];
    }

    public function dispatch(StartWorkerJobMessage $message): Envelope
    {
        return $this->doDispatch($message, $this->dispatchDelay);
    }

    public function dispatchForMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $machine = $event->current;

        $job = $this->jobRepository->find($machine->id);
        if (!$job instanceof Job) {
            return;
        }

        $this->doDispatch(new StartWorkerJobMessage($event->authenticationToken, $machine->id, $event->ipAddress));
    }

    private function doDispatch(StartWorkerJobMessage $message, int $delay = 0): Envelope
    {
        return $this->messageBus->dispatch(
            new Envelope($message, $delay > 0 ? [new DelayStamp($delay)] : [])
        );
    }
}
