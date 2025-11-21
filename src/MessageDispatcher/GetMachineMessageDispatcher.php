<?php

declare(strict_types=1);

namespace App\MessageDispatcher;

use App\Event\AuthenticatingEventInterface as AuthenticatingEvent;
use App\Event\JobEventInterface as JobEvent;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineEventInterface as MachineEvent;
use App\Event\MachineRetrievedEvent;
use App\Message\GetMachineMessage;
use App\Messenger\NonDelayedStamp;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

readonly class GetMachineMessageDispatcher extends AbstractMessageDispatcher implements EventSubscriberInterface
{
    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineCreationRequestedEvent::class => [
                ['dispatchImmediately', 100],
            ],
            MachineRetrievedEvent::class => [
                ['dispatch', 100],
            ],
        ];
    }

    public function dispatch(MachineRetrievedEvent $event): void
    {
        $this->doDispatch($event);
    }

    public function dispatchImmediately(MachineCreationRequestedEvent $event): void
    {
        $this->doDispatch($event, [new NonDelayedStamp()]);
    }

    /**
     * @param StampInterface[] $stamps
     */
    private function doDispatch(AuthenticatingEvent&JobEvent&MachineEvent $event, array $stamps = []): void
    {
        $message = new GetMachineMessage(
            $event->getAuthenticationToken(),
            $event->getJobId(),
            $event->getMachine()
        );

        if ($this->isNeverReady($message)) {
            return;
        }

        $this->messageDispatcher->dispatch($message, $stamps);
    }
}
