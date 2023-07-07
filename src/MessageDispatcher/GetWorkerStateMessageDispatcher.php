<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\WorkerJobStartRequestedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Message\GetWorkerStateMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GetWorkerStateMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRemoteRequestMessageDispatcher $messageDispatcher,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerJobStartRequestedEvent::class => [
                ['dispatchForWorkerJobStartRequestedEvent', 100],
            ],
            WorkerStateRetrievedEvent::class => [
                ['dispatchForWorkerStateRetrievedEvent', 100],
            ],
        ];
    }

    public function dispatchForWorkerJobStartRequestedEvent(WorkerJobStartRequestedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new GetWorkerStateMessage($event->jobId, $event->machineIpAddress)
        );
    }

    public function dispatchForWorkerStateRetrievedEvent(WorkerStateRetrievedEvent $event): void
    {
        if (!$event->state->applicationState->isEndState) {
            $this->messageDispatcher->dispatch(
                new GetWorkerStateMessage($event->jobId, $event->machineIpAddress)
            );
        }
    }
}
