<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\GetResultsJobStateMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GetResultsJobStateMessageDispatcher implements EventSubscriberInterface
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
            ResultsJobCreatedEvent::class => [
                ['dispatchForResultsJobCreatedEvent', 100],
            ],
            ResultsJobStateRetrievedEvent::class => [
                ['dispatchForResultsJobStateRetrievedEvent', 100],
            ],
        ];
    }

    public function dispatchForResultsJobCreatedEvent(ResultsJobCreatedEvent $event): void
    {
        $this->messageDispatcher->dispatch(
            new GetResultsJobStateMessage($event->authenticationToken, $event->jobId)
        );
    }

    public function dispatchForResultsJobStateRetrievedEvent(ResultsJobStateRetrievedEvent $event): void
    {
        if (is_string($event->resultsJobState->endState)) {
            return;
        }

        $this->messageDispatcher->dispatch(
            new GetResultsJobStateMessage($event->authenticationToken, $event->jobId)
        );
    }
}
