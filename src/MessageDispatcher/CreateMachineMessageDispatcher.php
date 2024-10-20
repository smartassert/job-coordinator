<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
use App\Message\CreateMachineMessage;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use App\Repository\SerializedSuiteRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateMachineMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly ResultsJobRepository $resultsJobRepository,
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
        $job = $this->jobRepository->find($event->jobId);
        if (null === $job) {
            return;
        }

        $resultsJob = $this->resultsJobRepository->find($job->id);
        if (null === $resultsJob) {
            return;
        }

        $serializedSuite = $this->serializedSuiteRepository->find($job->id);
        if (null === $serializedSuite) {
            return;
        }

        if (!$serializedSuite->isPrepared()) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new CreateMachineMessage($event->authenticationToken, $event->jobId)
        );
    }
}
