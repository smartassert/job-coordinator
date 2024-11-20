<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Message\GetWorkerJobMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetWorkerJobMessageDispatcher extends AbstractMessageDispatcher implements EventSubscriberInterface
{
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
        if ($this->isNeverReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new GetWorkerJobMessage($event->getJobId(), $event->getMachineIpAddress())
        );
    }

    public function dispatchForWorkerStateRetrievedEvent(WorkerStateRetrievedEvent $event): void
    {
        if ($this->isNeverReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatch(
            new GetWorkerJobMessage($event->getJobId(), $event->getMachineIpAddress())
        );
    }
}
