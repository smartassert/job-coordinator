<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Message\JobRemoteRequestMessageInterface;
use App\Messenger\NonDelayedStamp;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

class JobRemoteRequestMessageDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param StampInterface[] $stamps
     */
    public function dispatch(JobRemoteRequestMessageInterface $message, array $stamps = []): Envelope
    {
        $this->eventDispatcher->dispatch(new JobRemoteRequestMessageCreatedEvent($message));

        return $this->messageBus->dispatch(new Envelope($message, $stamps));
    }

    public function dispatchWithNonDelayedStamp(JobRemoteRequestMessageInterface $message): Envelope
    {
        return $this->dispatch($message, [new NonDelayedStamp()]);
    }
}
