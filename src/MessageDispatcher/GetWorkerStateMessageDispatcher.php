<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\CreateWorkerJobRequestedEvent;
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
            CreateWorkerJobRequestedEvent::class => [
                ['dispatchForCreateWorkerJobRequestedEvent', 100],
            ],
            WorkerStateRetrievedEvent::class => [
                ['dispatchForWorkerStateRetrievedEvent', 100],
            ],
        ];
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForCreateWorkerJobRequestedEvent(CreateWorkerJobRequestedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new GetWorkerStateMessage($event->getJobId(), $event->getMachineIpAddress())
        );
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForWorkerStateRetrievedEvent(WorkerStateRetrievedEvent $event): void
    {
        $this->messageDispatcher->dispatch(
            new GetWorkerStateMessage($event->getJobId(), $event->getMachineIpAddress())
        );
    }
}
