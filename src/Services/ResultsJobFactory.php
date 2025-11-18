<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\ResultsJob;
use App\Event\ResultsJobCreatedEvent;
use App\Repository\ResultsJobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResultsJobFactory implements EventSubscriberInterface
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
            ResultsJobCreatedEvent::class => [
                ['createOnResultsJobCreatedEvent', 1000],
            ],
        ];
    }

    public function createOnResultsJobCreatedEvent(ResultsJobCreatedEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $resultsJob = $this->resultsJobRepository->find($job->getId());
        if (null === $resultsJob) {
            $resultsJob = new ResultsJob(
                $job->getId(),
                $event->resultsJob->token,
                $event->resultsJob->state->state,
                $event->resultsJob->state->endState
            );
            $this->resultsJobRepository->save($resultsJob);
        }
    }
}
