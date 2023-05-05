<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineIsActiveEvent;
use App\Message\StartWorkerJobMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class StartWorkerJobMessageDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
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
        ];
    }

    public function dispatch(StartWorkerJobMessage $message): Envelope
    {
        return $this->messageBus->dispatch($message);
    }

    public function dispatchForMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $this->messageBus->dispatch(
            new StartWorkerJobMessage($event->authenticationToken, $event->jobId, $event->ipAddress)
        );
    }
}
