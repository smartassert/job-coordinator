<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\MachineIsActiveEvent;
use App\Message\StartWorkerJobMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;

class StartWorkerJobMessageDispatcher extends AbstractDeferredMessageDispatcher implements EventSubscriberInterface
{
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
        return $this->doDispatch($message);
    }

    public function dispatchForMachineIsActiveEvent(MachineIsActiveEvent $event): void
    {
        $this->doDispatch(new StartWorkerJobMessage($event->authenticationToken, $event->jobId, $event->ipAddress));
    }
}
