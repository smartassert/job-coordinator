<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Message\TerminateMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class TerminateMachineMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ResultsJobStateRetrievedEvent::class => [
                ['dispatchImmediately', 100],
            ],
        ];
    }

    public function dispatchImmediately(ResultsJobStateRetrievedEvent $event): void
    {
        $message = new TerminateMachineMessage($event->getAuthenticationToken(), $event->getJobId());
        $readiness = $this->readinessAssessor->isReady($message);
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp($message);
    }
}
