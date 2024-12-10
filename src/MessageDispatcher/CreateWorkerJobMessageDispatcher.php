<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineIsActiveEvent;
use App\Event\MessageNotYetHandleableEvent;
use App\Message\CreateWorkerJobMessage;
use App\Message\JobRemoteRequestMessageInterface;
use App\MessageDispatcher\AbstractRedispatchingMessageDispatcher as BaseMessageDispatcher;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\JobStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateWorkerJobMessageDispatcher extends BaseMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        JobRemoteRequestMessageDispatcher $messageDispatcher,
        private JobStore $jobStore,
        ReadinessAssessorInterface $readinessAssessor,
    ) {
        parent::__construct($messageDispatcher, $readinessAssessor);
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineIsActiveEvent::class => [
                ['dispatchImmediately', 100],
            ],
            MessageNotYetHandleableEvent::class => [
                ['redispatch', 100],
            ],
        ];
    }

    public function dispatchImmediately(MachineIsActiveEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job || $this->isNeverReady($event->getJobId())) {
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

    protected function handles(JobRemoteRequestMessageInterface $message): bool
    {
        return $message instanceof CreateWorkerJobMessage;
    }
}
