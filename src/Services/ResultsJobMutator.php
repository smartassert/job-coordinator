<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\ResultsJob;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Repository\ResultsJobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResultsJobMutator implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly ResultsJobRepository $resultsJobRepository,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ResultsJobStateRetrievedEvent::class => [
                ['setState', 1000],
            ],
        ];
    }

    public function setState(ResultsJobStateRetrievedEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $resultsJob = $this->resultsJobRepository->find($job->getId());
        if (!$resultsJob instanceof ResultsJob) {
            return;
        }

        $resultsJob->setState($event->resultsJobState->state);

        if (null !== $event->resultsJobState->endState) {
            $resultsJob->setEndState($event->resultsJobState->endState);
        }

        $this->resultsJobRepository->save($resultsJob);
    }
}
