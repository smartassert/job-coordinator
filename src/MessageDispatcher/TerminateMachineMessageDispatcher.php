<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\ResultsJobStateRetrievedEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
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

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatch(ResultsJobStateRetrievedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(new TerminateMachineMessage(
            $event->authenticationToken,
            $event->getJobId(),
        ));
    }
}
