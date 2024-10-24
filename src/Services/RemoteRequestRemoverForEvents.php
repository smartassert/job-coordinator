<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\RemoteRequestType;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineTerminationRequestedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Event\WorkerJobStartRequestedEvent;
use App\Event\WorkerStateRetrievedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RemoteRequestRemoverForEvents implements EventSubscriberInterface
{
    public function __construct(
        private readonly RemoteRequestRemover $remoteRequestRemover,
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
        $this->remoteRequestRemover->removeForJobAndType($event->getJobId(), RemoteRequestType::MACHINE_CREATE);
    }

    public function removeResultsCreateRequests(ResultsJobCreatedEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->jobId, RemoteRequestType::RESULTS_CREATE);
    }

    public function removeSerializedSuiteCreateRequests(SerializedSuiteCreatedEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE);
    }

    public function removeMachineGetRequests(MachineRetrievedEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->getJobId(), RemoteRequestType::MACHINE_GET);
    }

    public function removeSerializedSuiteGetRequests(SerializedSuiteRetrievedEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->jobId, RemoteRequestType::SERIALIZED_SUITE_GET);
    }

    public function removeWorkerJobStartRequests(WorkerJobStartRequestedEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->jobId, RemoteRequestType::MACHINE_START_JOB);
    }

    public function removeResultsStateGetRequests(ResultsJobStateRetrievedEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->jobId, RemoteRequestType::RESULTS_STATE_GET);
    }

    public function removeMachineTerminationRequests(MachineTerminationRequestedEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->getJobId(), RemoteRequestType::MACHINE_TERMINATE);
    }

    public function removeWorkerStateGetRequests(WorkerStateRetrievedEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->jobId, RemoteRequestType::MACHINE_STATE_GET);
    }
}
