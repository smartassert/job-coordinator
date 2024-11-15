<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineIsActiveEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Message\CreateWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\JobStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateWorkerJobMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private JobRemoteRequestMessageDispatcher $messageDispatcher,
        private JobStore $jobStore,
        private ReadinessAssessorInterface $readinessAssessor,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineIsActiveEvent::class => [
                ['dispatchForMachineIsActiveEvent', 100],
            ],
            MessageNotYetHandleableEvent::class => [
                ['reDispatch', 100],
            ],
        ];
    }

    public function dispatchForMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        if (MessageHandlingReadiness::NOW !== $this->readinessAssessor->isReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new CreateWorkerJobMessage(
                $event->getAuthenticationToken(),
                $event->getJobId(),
                $job->getMaximumDurationInSeconds(),
                $event->ipAddress
            )
        );
    }

    public function reDispatch(MessageNotYetHandleableEvent $event): void
    {
        $message = $event->message;
        if (!$message instanceof CreateWorkerJobMessage) {
            return;
        }

        $this->messageDispatcher->dispatch($event->message);
    }
}
