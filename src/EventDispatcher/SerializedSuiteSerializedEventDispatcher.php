<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Event\SerializedSuiteRetrievedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SerializedSuiteSerializedEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SerializedSuiteRetrievedEvent::class => [
                ['dispatchSerializedSuiteSerializedEventOnSerializedSuiteRetrievedEvent', 100],
            ],
        ];
    }

    public function dispatchSerializedSuiteSerializedEventOnSerializedSuiteRetrievedEvent(
        SerializedSuiteRetrievedEvent $event
    ): void {
        if (!$event->serializedSuite->isPrepared()) {
            return;
        }

        $this->eventDispatcher->dispatch(new SerializedSuiteSerializedEvent(
            $event->authenticationToken,
            $event->getJobId(),
            $event->serializedSuite->getId()
        ));
    }
}
