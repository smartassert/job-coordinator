<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\GetResultsJobStateMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetResultsJobStateMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
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
        if (MessageHandlingReadiness::NOW !== $this->readinessAssessor->isReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatch(
            new GetResultsJobStateMessage($event->getAuthenticationToken(), $event->getJobId())
        );
    }
}
