<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\AuthenticatingEventInterface;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\JobEventInterface;
use App\Event\ResultsJobRetrievedEvent;
use App\Message\GetResultsJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetResultsJobMessageDispatcher implements EventSubscriberInterface
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
            CreateWorkerJobRequestedEvent::class => [
                ['dispatchImmediately', 100],
            ],
            ResultsJobRetrievedEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    public function dispatchImmediately(CreateWorkerJobRequestedEvent $event): void
    {
        $this->createAndHandleMessage(
            $event,
            fn (GetResultsJobMessage $message) => $this->messageDispatcher->dispatchWithNonDelayedStamp($message)
        );
    }

    public function dispatch(ResultsJobRetrievedEvent $event): void
    {
        $this->createAndHandleMessage(
            $event,
            fn (GetResultsJobMessage $message) => $this->messageDispatcher->dispatch($message)
        );
    }

    private function createAndHandleMessage(
        AuthenticatingEventInterface&JobEventInterface $event,
        callable $action
    ): void {
        $message = new GetResultsJobMessage($event->getAuthenticationToken(), $event->getJobId());
        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $action($message);
    }
}
