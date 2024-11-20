<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MessageNotYetHandleableEvent;
use App\Event\ResultsJobCreatedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use App\Message\CreateMachineMessage;
use App\Message\JobRemoteRequestMessageInterface;
use App\MessageDispatcher\AbstractRedispatchingMessageDispatcher as BaseMessageDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class CreateMachineMessageDispatcher extends BaseMessageDispatcher implements EventSubscriberInterface
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
                ['redispatch', 100],
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

    protected function handles(JobRemoteRequestMessageInterface $message): bool
    {
        return $message instanceof CreateMachineMessage;
    }
}
