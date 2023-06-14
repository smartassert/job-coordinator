<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\TerminateMachineMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TerminateMachineMessageDispatcher implements EventSubscriberInterface
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
            ResultsJobStateRetrievedEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    public function dispatch(ResultsJobStateRetrievedEvent $event): void
    {
        if (null === $event->resultsJobState->endState) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(new TerminateMachineMessage(
            $event->authenticationToken,
            $event->jobId,
        ));
    }
}
