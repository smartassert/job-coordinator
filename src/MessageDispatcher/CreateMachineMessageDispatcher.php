<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Enum\MessageHandlingReadiness;
use App\Event\MessageNotYetHandleableEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\CreateMachineMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateMachineMessageDispatcher extends AbstractMessageDispatcher implements EventSubscriberInterface
{
    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ResultsJobCreatedEvent::class => [
                ['dispatch', 100],
            ],
            SerializedSuiteSerializedEvent::class => [
                ['dispatch', 100],
            ],
            MessageNotYetHandleableEvent::class => [
                ['reDispatch', 100],
            ],
        ];
    }

    public function dispatch(ResultsJobCreatedEvent|SerializedSuiteSerializedEvent $event): void
    {
        if ($this->isNeverReady($event->getJobId())) {
            return;
        }

        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new CreateMachineMessage($event->getAuthenticationToken(), $event->getJobId())
        );
    }

    public function reDispatch(MessageNotYetHandleableEvent $event): void
    {
        $message = $event->message;

        if (
            !$message instanceof CreateMachineMessage
            || MessageHandlingReadiness::NEVER === $this->readinessAssessor->isReady($message->getJobId())
        ) {
            return;
        }

        $this->messageDispatcher->dispatch($message);
    }
}
