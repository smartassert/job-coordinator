<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\FooEvent;
use App\Event\MachineIsActiveEvent;
use App\Exception\NonRepeatableMessageAlreadyDispatchedException;
use App\Message\CreateWorkerJobMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateWorkerJobMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly JobRemoteRequestMessageDispatcher $messageDispatcher,
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
            FooEvent::class => [
                ['reDispatch', 100],
            ],
        ];
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function dispatchForMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $this->messageDispatcher->dispatchWithNonDelayedStamp(
            new CreateWorkerJobMessage($event->getAuthenticationToken(), $event->getJobId(), $event->ipAddress)
        );
    }

    /**
     * @throws NonRepeatableMessageAlreadyDispatchedException
     */
    public function reDispatch(FooEvent $event): void
    {
        $message = $event->message;
        if (!$message instanceof CreateWorkerJobMessage) {
            return;
        }

        $this->messageDispatcher->dispatch($event->message);
    }
}
