<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestStateTracker;

use App\Event\HasJobRemoteRequestMessageInterface;
use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Event\MessageNotHandleableEvent;
use App\Message\JobRemoteRequestMessageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class RemoteRequestStateTracker implements EventSubscriberInterface
{
    /**
     * @param iterable<EventHandlerInterface> $handlers
     */
    public function __construct(
        private readonly iterable $handlers,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => [
                ['handleEvent', 10000],
            ],
            WorkerMessageHandledEvent::class => [
                ['handleEvent', 10000],
            ],
            WorkerMessageReceivedEvent::class => [
                ['handleEvent', 10000],
            ],
            JobRemoteRequestMessageCreatedEvent::class => [
                ['handleEvent', 10000],
            ],
            MessageNotHandleableEvent::class => [
                ['handleEvent', 10000],
            ],
        ];
    }

    public function handleEvent(AbstractWorkerMessageEvent|HasJobRemoteRequestMessageInterface $event): void
    {
        $message = $event instanceof HasJobRemoteRequestMessageInterface
            ? $event->getMessage()
            : $event->getEnvelope()->getMessage();

        if (!$message instanceof JobRemoteRequestMessageInterface) {
            return;
        }

        foreach ($this->handlers as $handler) {
            $result = $handler->handle($event, $message);

            if (true === $result) {
                return;
            }
        }
    }
}
