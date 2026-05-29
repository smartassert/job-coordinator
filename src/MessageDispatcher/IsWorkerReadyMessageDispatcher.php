<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MachineIsActiveEvent;
use App\Event\MessageNotHandleableEvent;
use App\Message\IsWorkerReadyMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class IsWorkerReadyMessageDispatcher implements EventSubscriberInterface
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
        $message = new IsWorkerReadyMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $event->ipAddress
        );
        $readiness = $this->readinessAssessor->isReady($message->getJobId());
        if (MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp($message);
    }

    public function redispatch(MessageNotHandleableEvent $event): void
    {
        $message = $event->message;
        if (!$message instanceof IsWorkerReadyMessage) {
            return;
        }

        $readiness = $this->readinessAssessor->isReady($message->getJobId());

        if (MessageHandlingReadiness::NEVER === $event->readiness || MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatch($message);
    }
}
