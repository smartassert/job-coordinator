<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\AuthenticatingEventInterface;
use App\Event\CreateWorkerJobRequestedEvent;
use App\Event\JobEventInterface;
use App\Event\MachineIpAddressInterface;
use App\Event\WorkerJobRetrievedEvent;
use App\Message\GetWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class GetWorkerJobMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
        private ReadinessAssessorInterface $readinessAssessor,
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

    public function dispatchImmediately(
        AuthenticatingEventInterface&JobEventInterface&MachineIpAddressInterface $event
    ): void {
        $message = new GetWorkerJobMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $event->getMachineIpAddress()
        );

        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp($message);
    }
}
