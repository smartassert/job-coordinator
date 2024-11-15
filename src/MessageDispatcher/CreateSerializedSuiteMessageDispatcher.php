<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\JobCreatedEvent;
use App\Message\CreateSerializedSuiteMessage;
use App\Repository\SerializedSuiteRepository;
use App\Services\JobStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateSerializedSuiteMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
        private SerializedSuiteRepository $serializedSuiteRepository,
        private JobStore $jobStore,
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
        if ($this->serializedSuiteRepository->has($event->getJobId())) {
            return;
        }

        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new CreateSerializedSuiteMessage(
                $event->getAuthenticationToken(),
                $job->getId(),
                $job->getSuiteId(),
                $event->parameters
            )
        );
    }
}
