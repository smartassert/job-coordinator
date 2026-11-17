<?php

declare(strict_types=1);

namespace App\RemoteEvent;

use App\Enum\RemoteEventName;
use App\Event\ResultsJobRetrievedEvent;
use App\Repository\ResultsJobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\JobFactory;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(RemoteEventName::RESULTS_JOB_STATE_CHANGED->value)]
final readonly class ResultsJobStateChangedWebhookConsumer implements ConsumerInterface
{
    public function __construct(
        private JobFactory $jobFactory,
        private ResultsJobRepository $resultsJobRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function consume(RemoteEvent $event): void
    {
        if (RemoteEventName::RESULTS_JOB_STATE_CHANGED->value !== $event->getName()) {
            return;
        }

        $eventResultsJob = $this->jobFactory->createForExistingJob($event->getPayload());
        if (null === $eventResultsJob) {
            return;
        }

        $resultsJobEntity = $this->resultsJobRepository->findOneBy(['jobId' => $eventResultsJob->getLabel()]);
        if (null === $resultsJobEntity) {
            return;
        }

        $this->eventDispatcher->dispatch(new ResultsJobRetrievedEvent($eventResultsJob));
    }
}
