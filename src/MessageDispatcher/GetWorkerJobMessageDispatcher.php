<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\JobEventInterface;
use App\Event\WorkerJobRetrievedEvent;
use App\Message\GetWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Repository\MachineRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetWorkerJobMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
        private MachineRepository $machineRepository,
    ) {}

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CreateWorkerJobRequestedEvent::class => [
                ['dispatchImmediately', 100],
            ],
            WorkerJobRetrievedEvent::class => [
                ['dispatchImmediately', 100],
            ],
        ];
    }

    public function dispatchImmediately(JobEventInterface $event): void
    {
        $machine = $this->machineRepository->find($event->getJobId());
        if (null === $machine) {
            return;
        }

        $ipAddress = $machine->getIpAddress();
        if (null === $ipAddress) {
            return;
        }

        $message = new GetWorkerJobMessage($event->getJobId());

        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp($message);
    }
}
