<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\RemoteRequestType;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteCreatedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
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
                ['removeMachineCreateRemoteRequestsForMachineIsActiveEvent', 0],
            ],
            ResultsJobCreatedEvent::class => [
                ['removeResultsCreateRemoteRequestsForResultsJobCreatedEvent', 0],
            ],
            SerializedSuiteCreatedEvent::class => [
                ['removeSerializedSuiteCreateRequestsForSerializedSuiteCreatedEvent', 0],
            ],
            MachineRetrievedEvent::class => [
                ['removeMachineGetRemoteRequestsForMachineRetrievedEvent', 0],
            ],
            SerializedSuiteRetrievedEvent::class => [
                ['removeSerializedSuiteGetRemoteRequestsForSerializedSuiteRetrievedEvent', 0],
            ],
        ];
    }

    public function removeMachineCreateRemoteRequestsForMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->jobId, RemoteRequestType::MACHINE_CREATE);
    }

    public function removeResultsCreateRemoteRequestsForResultsJobCreatedEvent(ResultsJobCreatedEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->jobId, RemoteRequestType::RESULTS_CREATE);
    }

    public function removeSerializedSuiteCreateRequestsForSerializedSuiteCreatedEvent(
        SerializedSuiteCreatedEvent $event
    ): void {
        $this->remoteRequestRemover->removeForJobAndType($event->jobId, RemoteRequestType::SERIALIZED_SUITE_CREATE);
    }

    public function removeMachineGetRemoteRequestsForMachineRetrievedEvent(MachineRetrievedEvent $event): void
    {
        $this->remoteRequestRemover->removeForJobAndType($event->current->id, RemoteRequestType::MACHINE_GET);
    }

    public function removeSerializedSuiteGetRemoteRequestsForSerializedSuiteRetrievedEvent(
        SerializedSuiteRetrievedEvent $event
    ): void {
        $this->remoteRequestRemover->removeForJobAndType($event->jobId, RemoteRequestType::SERIALIZED_SUITE_GET);
    }
}
