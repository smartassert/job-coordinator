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
        $jobId = $event->getJobId();
        $job = $this->jobRepository->find($jobId);
        if (!$job instanceof Job) {
            return;
        }

        $this->setComponentState($jobId, WorkerComponentName::APPLICATION, $event->state->applicationState);
        $this->setComponentState($jobId, WorkerComponentName::COMPILATION, $event->state->compilationState);
        $this->setComponentState($jobId, WorkerComponentName::EXECUTION, $event->state->executionState);
        $this->setComponentState($jobId, WorkerComponentName::EVENT_DELIVERY, $event->state->eventDeliveryState);
    }

    /**
     * @param non-empty-string $jobId
     */
    private function setComponentState(
        string $jobId,
        WorkerComponentName $componentName,
        ComponentState $componentState
    ): void {
        $componentStateEntity = $this->workerComponentStateRepository->findOneBy([
            'jobId' => $jobId,
            'componentName' => $componentName,
        ]);

        if (null === $componentStateEntity) {
            $componentStateEntity = new WorkerComponentState($jobId, $componentName);
        }

        $componentStateEntity = $componentStateEntity
            ->setState($componentState->state)
            ->setIsEndState($componentState->isEndState)
        ;

        $this->workerComponentStateRepository->save($componentStateEntity);
    }
}
