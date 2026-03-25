<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\WorkerJobCreationFailure;
use App\Event\CreateWorkerJobFailedEvent;
use App\Repository\WorkerJobCreationFailureRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class WorkerJobCreationFailureRecorder implements EventSubscriberInterface
{
    public function __construct(
        private WorkerJobCreationFailureRepository $repository,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CreateWorkerJobFailedEvent::class => [
                ['foo', 1000],
            ],
        ];
    }

    public function foo(CreateWorkerJobFailedEvent $event): void
    {
        $entity = $this->repository->find($event->getJobId());
        if (null !== $entity) {
            return;
        }

        $this->repository->save(
            new WorkerJobCreationFailure($event->getJobId(), $event->getStage(), $event->getException())
        );
    }
}
