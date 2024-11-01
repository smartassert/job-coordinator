<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\RemoteRequestAction;
use App\Enum\RemoteRequestEntity;
use App\Event\JobEventInterface;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineTerminationRequestedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Event\WorkerJobStartRequestedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Model\RemoteRequestType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class RemoteRequestRemoverForEvents implements EventSubscriberInterface
{
    public function __construct(
        private RemoteRequestRemover $remoteRequestRemover,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineIsActiveEvent::class => [
                ['removeMachineCreateRequests', 0],
            ],
            ResultsJobCreatedEvent::class => [
                ['removeResultsCreateRequests', 0],
            ],
            SerializedSuiteCreatedEvent::class => [
                ['removeSerializedSuiteCreateRequests', 0],
            ],
            MachineRetrievedEvent::class => [
                ['removeMachineGetRequests', 0],
            ],
            SerializedSuiteRetrievedEvent::class => [
                ['removeSerializedSuiteGetRequests', 0],
            ],
            WorkerJobStartRequestedEvent::class => [
                ['removeWorkerJobStartRequests', 0],
            ],
            ResultsJobStateRetrievedEvent::class => [
                ['removeResultsStateGetRequests', 0],
            ],
            MachineTerminationRequestedEvent::class => [
                ['removeMachineTerminationRequests', 0],
            ],
            WorkerStateRetrievedEvent::class => [
                ['removeWorkerStateGetRequests', 0],
            ],
        ];
    }

    public function removeMachineCreateRequests(MachineIsActiveEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(RemoteRequestEntity::MACHINE, RemoteRequestAction::CREATE)
        );
    }

    public function removeResultsCreateRequests(ResultsJobCreatedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(RemoteRequestEntity::RESULTS_JOB, RemoteRequestAction::CREATE)
        );
    }

    public function removeSerializedSuiteCreateRequests(SerializedSuiteCreatedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(RemoteRequestEntity::SERIALIZED_SUITE, RemoteRequestAction::CREATE)
        );
    }

    public function removeMachineGetRequests(MachineRetrievedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(RemoteRequestEntity::MACHINE, RemoteRequestAction::RETRIEVE)
        );
    }

    public function removeSerializedSuiteGetRequests(SerializedSuiteRetrievedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(RemoteRequestEntity::SERIALIZED_SUITE, RemoteRequestAction::RETRIEVE)
        );
    }

    public function removeWorkerJobStartRequests(WorkerJobStartRequestedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(RemoteRequestEntity::WORKER_JOB, RemoteRequestAction::CREATE)
        );
    }

    public function removeResultsStateGetRequests(ResultsJobStateRetrievedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(RemoteRequestEntity::RESULTS_JOB, RemoteRequestAction::RETRIEVE)
        );
    }

    public function removeMachineTerminationRequests(MachineTerminationRequestedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(RemoteRequestEntity::MACHINE, RemoteRequestAction::TERMINATE)
        );
    }

    public function removeWorkerStateGetRequests(WorkerStateRetrievedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(RemoteRequestEntity::WORKER_JOB, RemoteRequestAction::RETRIEVE)
        );
    }

    private function removeForEventAndType(JobEventInterface $event, RemoteRequestType $type): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->getJobId(), $type);
    }
}
