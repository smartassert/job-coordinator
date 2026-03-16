<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestStateTracker;

use App\Enum\RequestState;
use App\Message\JobRemoteRequestMessageInterface;
use App\Services\RemoteRequestStateMutator;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

readonly class WorkerMessageFailedEventHandler implements EventHandlerInterface
{
    public function __construct(
        private RemoteRequestStateMutator $mutator,
    ) {}

    public function handle(object $event, JobRemoteRequestMessageInterface $message): bool
    {
        if (!$event instanceof WorkerMessageFailedEvent) {
            return false;
        }

        $this->mutator->setRemoteRequestStateForMessage(
            $message,
            $event->willRetry() ? RequestState::HALTED : RequestState::FAILED
        );

        return true;
    }
}
