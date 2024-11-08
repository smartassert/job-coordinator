<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\GetResultsJobStateMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetResultsJobStateMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ResultsJobCreatedEvent::class => [
                ['dispatchForResultsJobEvent', 100],
            ],
            ResultsJobStateRetrievedEvent::class => [
                ['dispatchForResultsJobEvent', 100],
            ],
        ];
    }

    public function dispatchForResultsJobEvent(ResultsJobCreatedEvent|ResultsJobStateRetrievedEvent $event): void
    {
        $this->messageDispatcher->dispatch(
            new GetResultsJobStateMessage($event->getAuthenticationToken(), $event->getJobId())
        );
    }
}
