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
                ['setOnWorkerStateRetrievedEvent', 1000],
            ],
        ];
    }

    public function setOnWorkerStateRetrievedEvent(WorkerStateRetrievedEvent $event): void
    {
        $job = $this->jobRepository->find($event->jobId);
        if (!$job instanceof Job) {
            return;
        }

        $workerState = $this->workerStateRepository->find($job->id);
        if ($workerState instanceof WorkerState) {
            $workerState = $workerState
                ->setApplicationState($event->state->applicationState->state)
                ->setCompilationState($event->state->compilationState->state)
                ->setExecutionState($event->state->executionState->state)
                ->setEventDeliveryState($event->state->eventDeliveryState->state)
            ;
        } else {
            $workerState = new WorkerState(
                $job->id,
                $event->state->applicationState->state,
                $event->state->compilationState->state,
                $event->state->executionState->state,
                $event->state->eventDeliveryState->state,
            );
        }

        $this->workerStateRepository->save($workerState);
    }
}
