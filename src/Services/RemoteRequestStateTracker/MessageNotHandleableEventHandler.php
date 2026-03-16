<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestStateTracker;

use App\Enum\MessageHandlingReadiness;
use App\Enum\RequestState;
use App\Event\MessageNotHandleableEvent;
use App\Message\JobRemoteRequestMessageInterface;
use App\Services\RemoteRequestStateMutator;

readonly class MessageNotHandleableEventHandler implements EventHandlerInterface
{
    public function __construct(
        private RemoteRequestStateMutator $mutator,
    ) {}

    public function handle(object $event, JobRemoteRequestMessageInterface $message): bool
    {
        if (!$event instanceof MessageNotHandleableEvent) {
            return false;
        }

        $this->mutator->setRemoteRequestStateForMessage(
            $message,
            MessageHandlingReadiness::EVENTUALLY === $event->readiness ? RequestState::HALTED : RequestState::ABORTED
        );

        return true;
    }
}
