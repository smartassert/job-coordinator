<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\RemoteRequestFailure;
use App\Enum\RemoteRequestType;
use App\Event\MachineIsActiveEvent;
use App\Repository\JobRepository;
use App\Repository\RemoteRequestFailureRepository;
use App\Repository\RemoteRequestRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RemoteRequestRemover implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly RemoteRequestRepository $remoteRequestRepository,
        private readonly RemoteRequestFailureRepository $remoteRequestFailureRepository,
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
        ];
    }

    public function removeMachineCreateRemoteRequestsForMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $this->removeForJobAndType($event->jobId, RemoteRequestType::MACHINE_CREATE);
    }

    public function removeForJobAndType(string $jobId, RemoteRequestType $type): void
    {
        $job = $this->jobRepository->find($jobId);
        if (null === $job) {
            return;
        }

        $remoteRequests = $this->remoteRequestRepository->findBy([
            'jobId' => $job->id,
            'type' => $type,
        ]);

        foreach ($remoteRequests as $remoteRequest) {
            $this->remoteRequestRepository->remove($remoteRequest);

            $remoteRequestFailure = $remoteRequest->getFailure();
            if ($remoteRequestFailure instanceof RemoteRequestFailure) {
                $this->remoteRequestFailureRepository->remove($remoteRequestFailure);
            }
        }
    }
}
