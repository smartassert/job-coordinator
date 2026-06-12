<?php

declare(strict_types=1);

namespace App\Services;

use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\JobEventInterface;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineIsReadyEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineTerminationRequestedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobRetrievedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Event\WorkerStateRetrievedEvent;
use App\Model\RemoteRequestType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class RemoteRequestRemoverForEvents implements EventSubscriberInterface
{
    public function __construct(
        private RemoteRequestRemover $remoteRequestRemover,
    ) {}

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
            CreateWorkerJobRequestedEvent::class => [
                ['removeWorkerJobCreateRequests', 0],
            ],
            ResultsJobRetrievedEvent::class => [
                ['removeResultsStateGetRequests', 0],
            ],
            MachineTerminationRequestedEvent::class => [
                ['removeMachineTerminationRequests', 0],
            ],
            WorkerStateRetrievedEvent::class => [
                ['removeWorkerJobGetRequests', 0],
            ],
            MachineIsReadyEvent::class => [
                ['removeWorkerStateGetRequests', 0],
            ],
        ];
    }

    public function removeMachineCreateRequests(MachineIsActiveEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForMachineCreation());
    }

    public function removeResultsCreateRequests(ResultsJobCreatedEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForResultsJobCreation());
    }

    public function removeSerializedSuiteCreateRequests(SerializedSuiteCreatedEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForSerializedSuiteCreation());
    }

    public function removeMachineGetRequests(MachineRetrievedEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForMachineRetrieval());
    }

    public function removeSerializedSuiteGetRequests(SerializedSuiteRetrievedEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForSerializedSuiteRetrieval());
    }

    public function removeWorkerJobCreateRequests(CreateWorkerJobRequestedEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForWorkerJobCreation());
    }

    public function removeResultsStateGetRequests(ResultsJobRetrievedEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForResultsJobRetrieval());
    }

    public function removeMachineTerminationRequests(MachineTerminationRequestedEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForMachineTermination());
    }

    public function removeWorkerJobGetRequests(WorkerStateRetrievedEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForWorkerJobRetrieval());
    }

    public function removeWorkerStateGetRequests(MachineIsReadyEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForWorkerStateRetrieval());
    }

    private function removeForEventAndType(JobEventInterface $event, RemoteRequestType $type): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->getJobId(), $type);
    }
}
