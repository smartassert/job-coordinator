<?php

declare(strict_types=1);

namespace App\Services\RemoteRequestStateTracker;

use App\Enum\RequestState;
use App\Event\JobRemoteRequestMessageCreatedEvent;
use App\Message\JobRemoteRequestMessageInterface;
use App\Services\RemoteRequestIndexGenerator;
use App\Services\RemoteRequestStateMutator;

readonly class JobRemoteRequestMessageCreatedEventHandler implements EventHandlerInterface
{
    public function __construct(
        private RemoteRequestStateMutator $mutator,
        private RemoteRequestIndexGenerator $remoteRequestIndexGenerator,
    ) {}

    public function handle(object $event, JobRemoteRequestMessageInterface $message): bool
    {
        if (!$event instanceof JobRemoteRequestMessageCreatedEvent) {
            return false;
        }

        $message = $message->setIndex(
            $this->remoteRequestIndexGenerator->generate($message->getJobId(), $message->getRemoteRequestType())
        );

        $this->mutator->setRemoteRequestStateForMessage($message, RequestState::REQUESTING);

        return true;
    }
}
