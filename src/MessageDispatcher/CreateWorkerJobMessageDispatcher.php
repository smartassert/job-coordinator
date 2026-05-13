<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineIsActiveEvent;
use App\Event\MessageNotHandleableEvent;
use App\Message\CreateWorkerJobMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use App\Services\JobStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateWorkerJobMessageDispatcher extends AbstractMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        JobRemoteRequestMessageDispatcher $messageDispatcher,
        ReadinessAssessorInterface $readinessAssessor,
        private JobStore $jobStore,
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
            MessageNotHandleableEvent::class => [
                ['redispatch', 100],
            ],
        ];
    }

    public function dispatchImmediately(MachineIsActiveEvent $event): void
    {
        $job = $this->jobStore->retrieve($event->getJobId());
        if (null === $job) {
            return;
        }

        $message = new CreateWorkerJobMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $job->getMaximumDurationInSeconds(),
            $event->ipAddress
        );

        if ($this->isNeverReady($message)) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp($message);
    }

    public function redispatch(MessageNotHandleableEvent $event): void
    {
        $message = $event->message;
        if (!$message instanceof CreateWorkerJobMessage) {
            return;
        }

        $readiness = $this->readinessAssessor->isReady($message);

        if (MessageHandlingReadiness::NEVER === $event->readiness || MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatch($message);
    }
}
