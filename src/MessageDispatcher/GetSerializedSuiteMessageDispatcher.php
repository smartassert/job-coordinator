<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Message\GetSerializedSuiteMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetSerializedSuiteMessageDispatcher extends AbstractMessageDispatcher implements EventSubscriberInterface
{
    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SerializedSuiteCreatedEvent::class => [
                ['dispatchForSerializedSuiteEvent', 100],
            ],
            SerializedSuiteRetrievedEvent::class => [
                ['dispatchForSerializedSuiteEvent', 100],
            ],
        ];
    }

    public function dispatchForSerializedSuiteEvent(
        SerializedSuiteCreatedEvent|SerializedSuiteRetrievedEvent $event
    ): void {
        if ($this->isNeverReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(new GetSerializedSuiteMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $event->serializedSuite->getSuiteId(),
            $event->serializedSuite->getId()
        ));
    }
}
