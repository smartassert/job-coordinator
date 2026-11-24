<?php

declare(strict_types=1);

namespace App\RemoteEvent;

use App\Enum\RemoteEventType;
use App\Event\WorkerJobRetrievedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(RemoteEventType::WORKER_STATE_CHANGED->value)]
final readonly class WorkerApplicationStateChangedWebhookConsumer implements ConsumerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function consume(RemoteEvent $event): void
    {
        if (RemoteEventType::WORKER_STATE_CHANGED->value !== $event->getName()) {
            return;
        }

        if (!$event instanceof WorkerApplicationStateChangedEvent) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new WorkerJobRetrievedEvent(
                $event->job->getId(),
                $event->applicationState,
            ),
        );
    }
}
