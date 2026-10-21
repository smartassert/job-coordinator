<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\ResultsJob;
use App\Event\ResultsJobCreatedEvent;
use App\Model\MetaState;
use App\Repository\JobRepository;
use App\Repository\ResultsJobRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResultsJobFactory implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
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
        $job = $this->jobRepository->findOneBy(['id' => $event->getJobId()]);
        if (null === $job) {
            return;
        }

        $resultsJob = $this->resultsJobRepository->find($job->getId());
        if (null === $resultsJob) {
            $resultsJob = new ResultsJob(
                $job->getId(),
                $event->resultsJob->getEventNotifyUrl(),
                $event->resultsJob->getEventNotifyToken(),
                $event->resultsJob->getState()->state,
                $event->resultsJob->getState()->endState,
                new MetaState(
                    $event->resultsJob->getState()->metaState->ended,
                    $event->resultsJob->getState()->metaState->succeeded,
                    $event->resultsJob->getState()->metaState->pending,
                ),
            );
            $this->resultsJobRepository->save($resultsJob);
        }
    }
}
