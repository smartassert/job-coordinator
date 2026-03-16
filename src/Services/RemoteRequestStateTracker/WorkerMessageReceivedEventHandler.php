<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestStateTracker;

use App\Enum\RequestState;
use App\Message\JobRemoteRequestMessageInterface;
use App\Services\RemoteRequestStateMutator;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

readonly class WorkerMessageReceivedEventHandler implements EventHandlerInterface
{
    public function __construct(
        private RemoteRequestStateMutator $mutator,
    ) {}

    public function handle(object $event, JobRemoteRequestMessageInterface $message): bool
    {
        if (!$event instanceof WorkerMessageReceivedEvent) {
            return false;
        }

        $this->mutator->setRemoteRequestStateForMessage($message, RequestState::REQUESTING);

        return true;
    }
}
