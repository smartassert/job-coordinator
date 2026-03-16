<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestStateTracker;

use App\Enum\MessageState;
use App\Enum\RequestState;
use App\Message\JobRemoteRequestMessageInterface;
use App\Services\RemoteRequestStateMutator;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

readonly class WorkerMessageHandledEventHandler implements EventHandlerInterface
{
    public function __construct(
        private RemoteRequestStateMutator $mutator,
    ) {}

    public function handle(object $event, JobRemoteRequestMessageInterface $message): bool
    {
        if (!$event instanceof WorkerMessageHandledEvent) {
            return false;
        }

        $requestState = RequestState::SUCCEEDED;

        if (MessageState::HALTED === $message->getState()) {
            $requestState = RequestState::HALTED;
        }

        if (MessageState::STOPPED === $message->getState()) {
            $requestState = RequestState::FAILED;
        }

        $this->mutator->setRemoteRequestStateForMessage($message, $requestState);

        return true;
    }
}
