<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\WorkerJobStartRequestedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
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

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForWorkerJobStartRequestedEvent(WorkerJobStartRequestedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new GetWorkerStateMessage($event->jobId, $event->machineIpAddress)
        );
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForWorkerStateRetrievedEvent(WorkerStateRetrievedEvent $event): void
    {
        $this->messageDispatcher->dispatch(
            new GetWorkerStateMessage($event->jobId, $event->machineIpAddress)
        );
    }
}
