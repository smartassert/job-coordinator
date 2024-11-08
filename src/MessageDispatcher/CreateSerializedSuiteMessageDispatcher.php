<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobCreatedEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
use App\Message\CreateSerializedSuiteMessage;
use App\Repository\SerializedSuiteRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateSerializedSuiteMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRemoteRequestMessageDispatcher $messageDispatcher,
        private readonly SerializedSuiteRepository $serializedSuiteRepository,
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
        if ($this->serializedSuiteRepository->has($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new CreateSerializedSuiteMessage($event->getAuthenticationToken(), $event->getJobId(), $event->parameters)
        );
    }
}
