<?php

declare(strict_types=1);

namespace App\Services;

use App\Enum\RemoteRequestType;
use App\Event\MachineIsActiveEvent;
use App\Event\ResultsJobCreatedEvent;
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
}
