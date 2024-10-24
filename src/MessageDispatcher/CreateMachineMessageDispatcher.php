<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
use App\Message\CreateMachineMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateMachineMessageDispatcher implements EventSubscriberInterface
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
            ResultsJobCreatedEvent::class => [
                ['dispatch', 100],
            ],
            SerializedSuiteSerializedEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatch(ResultsJobCreatedEvent|SerializedSuiteSerializedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new CreateMachineMessage($event->authenticationToken, $event->getJobId())
        );
    }
}
