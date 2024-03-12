<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineRetrievedEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
use App\Message\GetMachineMessage;
use App\Repository\JobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GetMachineMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly JobRemoteRequestMessageDispatcher $messageDispatcher,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineCreationRequestedEvent::class => [
                ['dispatch', 100],
            ],
            MachineRetrievedEvent::class => [
                ['dispatchIfMachineNotInEndState', 100],
            ],
        ];
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchIfMachineNotInEndState(MachineRetrievedEvent $event): void
    {
        if ('end' === $event->current->getStateCategory()) {
            return;
        }

        $this->messageDispatcher->dispatch(new GetMachineMessage(
            $event->authenticationToken,
            $event->current->getId(),
            $event->current
        ));
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatch(MachineCreationRequestedEvent $event): void
    {
        $machine = $event->machine;
        $jobId = $machine->getId();

        $job = $this->jobRepository->find($jobId);
        if (null === $job) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new GetMachineMessage($event->authenticationToken, $machine->getId(), $machine)
        );
    }
}
