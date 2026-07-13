<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Message\GetSerializedSuiteMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetSerializedSuiteMessageDispatcher implements EventSubscriberInterface
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
            SerializedSuiteCreatedEvent::class => [
                ['dispatch', 100],
            ],
            SerializedSuiteRetrievedEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    public function dispatch(SerializedSuiteCreatedEvent|SerializedSuiteRetrievedEvent $event): void
    {
        $message = new GetSerializedSuiteMessage(
            $event->getJobId(),
            $event->serializedSuite->getSuiteId(),
            $event->serializedSuite->getId()
        );

        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatch($message);
    }
}
