<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MessageNotHandleableEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\CreateMachineMessage;
use App\ReadinessAssessor\ReadinessAssessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateMachineMessageDispatcher extends AbstractMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        JobRemoteRequestMessageDispatcher $messageDispatcher,
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
            ResultsJobCreatedEvent::class => [
                ['dispatchImmediately', 100],
            ],
            SerializedSuiteSerializedEvent::class => [
                ['dispatchImmediately', 100],
            ],
            MessageNotHandleableEvent::class => [
                ['redispatch', 100],
            ],
        ];
    }

    public function dispatchImmediately(ResultsJobCreatedEvent|SerializedSuiteSerializedEvent $event): void
    {
        $message = new CreateMachineMessage($event->getAuthenticationToken(), $event->getJobId());
        if ($this->isNeverReady($message)) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp($message);
    }

    public function redispatch(MessageNotHandleableEvent $event): void
    {
        $message = $event->message;
        if (!$message instanceof CreateMachineMessage) {
            return;
        }

        $readiness = $this->readinessAssessor->isReady($message);

        if (MessageHandlingReadiness::NEVER === $event->readiness || MessageHandlingReadiness::NEVER === $readiness) {
            return;
        }

        $this->messageDispatcher->dispatch($message);
    }
}
