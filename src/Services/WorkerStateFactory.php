<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\WorkerState;
use App\Event\WorkerStateRetrievedEvent;
use App\Repository\JobRepository;
use App\Repository\WorkerStateRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WorkerStateFactory implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly WorkerStateRepository $workerStateRepository,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStateRetrievedEvent::class => [
                ['createOnWorkerStateRetrievedEvent', 1000],
            ],
        ];
    }

    public function createOnWorkerStateRetrievedEvent(WorkerStateRetrievedEvent $event): void
    {
        $job = $this->jobRepository->find($event->jobId);
        if (!$job instanceof Job) {
            return;
        }

        $workerState = $this->workerStateRepository->find($job->id);
        if (null === $workerState) {
            $workerState = new WorkerState(
                $job->id,
                $event->state->applicationState->state,
                $event->state->compilationState->state,
                $event->state->executionState->state,
                $event->state->eventDeliveryState->state,
            );

            $this->workerStateRepository->save($workerState);
        }
    }
}
