<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Message\GetWorkerJobMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GetWorkerJobMessageDispatcher implements EventSubscriberInterface
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
            CreateWorkerJobRequestedEvent::class => [
                ['dispatchForCreateWorkerJobRequestedEvent', 100],
            ],
            WorkerStateRetrievedEvent::class => [
                ['dispatchForWorkerStateRetrievedEvent', 100],
            ],
        ];
    }

    public function dispatchForCreateWorkerJobRequestedEvent(CreateWorkerJobRequestedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new GetWorkerJobMessage($event->getJobId(), $event->getMachineIpAddress())
        );
    }

    public function dispatchForWorkerStateRetrievedEvent(WorkerStateRetrievedEvent $event): void
    {
        $this->messageDispatcher->dispatch(
            new GetWorkerJobMessage($event->getJobId(), $event->getMachineIpAddress())
        );
    }
}
