<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\GetResultsJobStateMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetResultsJobStateMessageDispatcher extends AbstractMessageDispatcher implements EventSubscriberInterface
{
    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ResultsJobCreatedEvent::class => [
                ['dispatch', 100],
            ],
            ResultsJobStateRetrievedEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    public function dispatch(ResultsJobCreatedEvent|ResultsJobStateRetrievedEvent $event): void
    {
        if ($this->isNeverReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatch(
            new GetResultsJobStateMessage($event->getAuthenticationToken(), $event->getJobId())
        );
    }
}
