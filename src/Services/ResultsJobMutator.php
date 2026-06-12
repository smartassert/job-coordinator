<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\ResultsJob;
use App\Event\ResultsJobRetrievedEvent;
use App\Model\MetaState;
use App\Repository\ResultsJobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResultsJobMutator implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly ResultsJobRepository $resultsJobRepository,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ResultsJobRetrievedEvent::class => [
                ['update', 1000],
            ],
        ];
    }

    public function update(ResultsJobRetrievedEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $resultsJob = $this->resultsJobRepository->find($job->getId());
        if (!$resultsJob instanceof ResultsJob) {
            return;
        }

        $remoteResultsJob = $event->resultsJob;

        $resultsJob->setState($remoteResultsJob->state->state);

        if (null !== $remoteResultsJob->state->endState) {
            $resultsJob->setEndState($remoteResultsJob->state->endState);
        }

        $resultsJob->setMetaState(new MetaState(
            $remoteResultsJob->state->metaState->ended,
            $remoteResultsJob->state->metaState->succeeded,
            $remoteResultsJob->state->metaState->pending,
        ));

        if ($remoteResultsJob->hasEvents) {
            $resultsJob->setHasEvents();
        }

        $this->resultsJobRepository->save($resultsJob);
    }
}
