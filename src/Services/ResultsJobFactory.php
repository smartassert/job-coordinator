<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\ResultsJob;
use App\Event\ResultsJobCreatedEvent;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResultsJobFactory implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly ResultsJobRepository $resultsJobRepository,
    ) {
    }

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
        $job = $this->jobRepository->find($event->jobId);
        if (!$job instanceof Job) {
            return;
        }

        $this->create(
            $job,
            $event->resultsJob->token,
            $event->resultsJob->state->state,
            $event->resultsJob->state->endState
        );
    }

    /**
     * @param non-empty-string  $token
     * @param non-empty-string  $state
     * @param ?non-empty-string $endState
     */
    public function create(Job $job, string $token, string $state, ?string $endState): ResultsJob
    {
        $resultsJob = $this->resultsJobRepository->find($job->id);
        if (null === $resultsJob) {
            $resultsJob = new ResultsJob($job->id, $token, $state, $endState);
            $this->resultsJobRepository->save($resultsJob);
        }

        return $resultsJob;
    }
}
