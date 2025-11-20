<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\JobComponent;
use App\Enum\RemoteRequestAction;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\JobEventInterface;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineTerminationRequestedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
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
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::RETRIEVE)
        );
    }

    public function removeSerializedSuiteGetRequests(SerializedSuiteRetrievedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(JobComponent::SERIALIZED_SUITE, RemoteRequestAction::RETRIEVE)
        );
    }

    public function removeWorkerJobCreateRequests(CreateWorkerJobRequestedEvent $event): void
    {
        $this->removeForEventAndType($event, RemoteRequestType::createForWorkerJobCreation());
    }

    public function removeResultsStateGetRequests(ResultsJobStateRetrievedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(JobComponent::RESULTS_JOB, RemoteRequestAction::RETRIEVE)
        );
    }

    public function removeMachineTerminationRequests(MachineTerminationRequestedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(JobComponent::MACHINE, RemoteRequestAction::TERMINATE)
        );
    }

    public function removeWorkerStateGetRequests(WorkerStateRetrievedEvent $event): void
    {
        $this->removeForEventAndType(
            $event,
            new RemoteRequestType(JobComponent::WORKER_JOB, RemoteRequestAction::RETRIEVE)
        );
    }

    private function removeForEventAndType(JobEventInterface $event, RemoteRequestType $type): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->getJobId(), $type);
    }
}
