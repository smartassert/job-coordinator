<?php

declare(strict_types=1);

namespace App\RemoteEvent;

use App\Event\ResultsJobRetrievedEvent;
use App\Repository\ResultsJobRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use SmartAssert\ResultsClient\JobFactory;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[AsRemoteEventConsumer(self::NAME)]
final readonly class ResultsJobStateChangedWebhookConsumer implements ConsumerInterface
{
    public const string NAME = 'results.job.state_changed';

    public function __construct(
        private JobFactory $jobFactory,
        private ResultsJobRepository $resultsJobRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function consume(RemoteEvent $event): void
    {
        if (self::NAME !== $event->getName()) {
            return;
        }

        $eventResultsJob = $this->jobFactory->create($event->getPayload());
        if (null === $eventResultsJob) {
            return;
        }

        $resultsJobEntity = $this->resultsJobRepository->findOneBy(['jobId' => $eventResultsJob->label]);
        if (null === $resultsJobEntity) {
            return;
        }

        $this->eventDispatcher->dispatch(new ResultsJobRetrievedEvent($eventResultsJob));
    }
}
