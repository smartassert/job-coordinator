<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Job;
use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;
use App\Event\WorkerStateRetrievedEvent;
use App\Repository\JobRepository;
use App\Repository\WorkerComponentStateRepository;
use SmartAssert\WorkerClient\Model\ComponentState;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WorkerComponentStateMutator implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly WorkerComponentStateRepository $workerComponentStateRepository,
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

        $this->setComponentState($job, WorkerComponentName::APPLICATION, $event->state->applicationState);
        $this->setComponentState($job, WorkerComponentName::COMPILATION, $event->state->compilationState);
        $this->setComponentState($job, WorkerComponentName::EXECUTION, $event->state->executionState);
        $this->setComponentState($job, WorkerComponentName::EVENT_DELIVERY, $event->state->eventDeliveryState);
    }

    private function setComponentState(
        Job $job,
        WorkerComponentName $componentName,
        ComponentState $componentState
    ): void {
        $componentStateEntity = $this->workerComponentStateRepository->find(
            WorkerComponentState::generateId($job->id, $componentName)
        );

        if ($componentStateEntity instanceof WorkerComponentState) {
            $componentStateEntity = $componentStateEntity
                ->setState($componentState->state)
                ->setIsEndState($componentState->isEndState)
            ;
        } else {
            $componentStateEntity = new WorkerComponentState(
                $job->id,
                $componentName,
                $componentState->state,
                $componentState->isEndState
            );
        }

        $this->workerComponentStateRepository->save($componentStateEntity);
    }
}
