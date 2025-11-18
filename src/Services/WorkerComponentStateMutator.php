<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerComponentState;
use App\Enum\WorkerComponentName;
use App\Event\WorkerStateRetrievedEvent;
use App\Repository\WorkerComponentStateRepository;
use SmartAssert\WorkerClient\Model\ComponentState;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WorkerComponentStateMutator implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobStore $jobStore,
        private readonly WorkerComponentStateRepository $workerComponentStateRepository,
    ) {}

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
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $this->setComponentState($job->getId(), WorkerComponentName::APPLICATION, $event->state->applicationState);
        $this->setComponentState($job->getId(), WorkerComponentName::COMPILATION, $event->state->compilationState);
        $this->setComponentState($job->getId(), WorkerComponentName::EXECUTION, $event->state->executionState);
        $this->setComponentState($job->getId(), WorkerComponentName::EVENT_DELIVERY, $event->state->eventDeliveryState);
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
