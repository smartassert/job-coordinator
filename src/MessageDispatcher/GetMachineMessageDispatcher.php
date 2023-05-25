<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineRequestedEvent;
use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use App\Messenger\NonDelayedStamp;
use App\Repository\JobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class GetMachineMessageDispatcher implements EventSubscriberInterface
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
            MachineRequestedEvent::class => [
                ['dispatch', 100],
            ],
            MachineRetrievedEvent::class => [
                ['dispatchIfMachineNotInEndState', 100],
            ],
        ];
    }

    public function dispatchIfMachineNotInEndState(MachineRetrievedEvent $event): void
    {
        if ('end' !== $event->current->stateCategory) {
            $this->messageBus->dispatch(new GetMachineMessage(
                $event->authenticationToken,
                $event->current->id,
                0,
                $event->current
            ));
        }
    }

    public function dispatch(MachineRequestedEvent $event): void
    {
        $machine = $event->machine;
        $jobId = $machine->id;

        $job = $this->jobRepository->find($jobId);
        if (null === $job) {
            return;
        }

        $this->messageBus->dispatch(new Envelope(
            new GetMachineMessage($event->authenticationToken, $machine->id, 0, $machine),
            [new NonDelayedStamp()]
        ));
    }
}
