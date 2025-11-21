<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MessageNotHandleableEvent;
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

    protected function handles(JobRemoteRequestMessageInterface $message): bool
    {
        return $message instanceof CreateMachineMessage;
    }
}
