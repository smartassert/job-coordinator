<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobCreatedEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
use App\Message\CreateResultsJobMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateResultsJobMessageDispatcher implements EventSubscriberInterface
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
            JobCreatedEvent::class => [
                ['dispatchForJobCreatedEvent', 100],
            ],
        ];
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForJobCreatedEvent(JobCreatedEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new CreateResultsJobMessage($event->getAuthenticationToken(), $event->getJobId())
        );
    }
}
