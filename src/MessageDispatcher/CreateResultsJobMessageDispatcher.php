<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\JobCreatedEvent;
use App\Message\CreateResultsJobMessage;
use App\ReadinessAssessor\CreateResultsJobReadinessAssessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateResultsJobMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRemoteRequestMessageDispatcher $messageDispatcher,
        private readonly CreateResultsJobReadinessAssessor $readinessAssessor,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            JobCreatedEvent::class => [
                ['dispatchForJobCreatedEvent', 100],
            ],
        ];
    }

    public function dispatchForJobCreatedEvent(JobCreatedEvent $event): void
    {
        if (MessageHandlingReadiness::NOW !== $this->readinessAssessor->isReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new CreateResultsJobMessage($event->getAuthenticationToken(), $event->getJobId())
        );
    }
}
