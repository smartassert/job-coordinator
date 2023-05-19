<?php

declare(strict_types=1);

namespace App\EventDispatcher;

use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MachineStateEventDispatcher implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineRetrievedEvent::class => [
                ['dispatchMachineStateChangeEvent', 100],
                ['dispatchMachineIsActiveEvent', 100],
            ],
        ];
    }

    public function dispatchMachineStateChangeEvent(MachineRetrievedEvent $event): void
    {
        if ($event->previous->state !== $event->current->state) {
            $this->eventDispatcher->dispatch(new MachineStateChangeEvent(
                $event->authenticationToken,
                $event->previous,
                $event->current,
            ));
        }
    }

    public function dispatchMachineIsActiveEvent(MachineRetrievedEvent $event): void
    {
        if (
            in_array($event->previous->stateCategory, ['unknown', 'finding', 'pre_active'])
            && 'active' === $event->current->stateCategory
        ) {
            $primaryIpAddress = $event->current->ipAddresses[0] ?? null;
            if (!is_string($primaryIpAddress)) {
                return;
            }

            $this->eventDispatcher->dispatch(new MachineIsActiveEvent(
                $event->authenticationToken,
                $event->current->id,
                $primaryIpAddress
            ));
        }
    }
}
