<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Message\GetWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetWorkerJobMessageDispatcher implements EventSubscriberInterface
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
            CreateWorkerJobRequestedEvent::class => [
                ['dispatchForCreateWorkerJobRequestedEvent', 100],
            ],
            WorkerStateRetrievedEvent::class => [
                ['dispatchForWorkerStateRetrievedEvent', 100],
            ],
        ];
    }

    public function dispatchForCreateWorkerJobRequestedEvent(CreateWorkerJobRequestedEvent $event): void
    {
        if (MessageHandlingReadiness::NOW !== $this->readinessAssessor->isReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new GetWorkerJobMessage($event->getJobId(), $event->getMachineIpAddress())
        );
    }

    public function dispatchForWorkerStateRetrievedEvent(WorkerStateRetrievedEvent $event): void
    {
        if (MessageHandlingReadiness::NOW !== $this->readinessAssessor->isReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatch(
            new GetWorkerJobMessage($event->getJobId(), $event->getMachineIpAddress())
        );
    }
}
