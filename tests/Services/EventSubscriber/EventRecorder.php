<?php

declare(strict_types=1);

namespace App\Tests\Services\EventSubscriber;

use App\Event\JobCreatedEvent;
use App\Event\MachineCreationRequestedEvent;
use App\Event\MachineIsActiveEvent;
use App\Event\MachineRetrievedEvent;
use App\Event\MachineStateChangeEvent;
use App\Event\MachineTerminationRequestedEvent;
use App\Event\ResultsJobStateRetrievedEvent;
use App\Event\SerializedSuiteRetrievedEvent;
use App\Event\SerializedSuiteSerializedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

class EventRecorder implements EventSubscriberInterface, \Countable
{
    /**
     * @var Event[]
     */
    private array $events = [];

    /**
     * @return array<class-string, array<mixed>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MachineStateChangeEvent::class => [
                ['addEvent', 1000],
            ],
            MachineIsActiveEvent::class => [
                ['addEvent', 1000],
            ],
            JobCreatedEvent::class => [
                ['addEvent', 1000],
            ],
            SerializedSuiteSerializedEvent::class => [
                ['addEvent', 1000],
            ],
            MachineCreationRequestedEvent::class => [
                ['addEvent', 1000],
            ],
            MachineRetrievedEvent::class => [
                ['addEvent', 1000],
            ],
            SerializedSuiteRetrievedEvent::class => [
                ['addEvent', 1000],
            ],
            MachineTerminationRequestedEvent::class => [
                ['addEvent', 1000],
            ],
            ResultsJobStateRetrievedEvent::class => [
                ['addEvent', 1000],
            ],
        ];
    }

    public function addEvent(Event $event): void
    {
        $this->events[] = $event;
    }

    public function getLatest(): ?Event
    {
        $latest = $this->events[count($this->events) - 1] ?? null;

        return $latest instanceof Event ? $latest : null;
    }

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @return Event[]
     */
    public function all(?string $eventName = null): array
    {
        if (null === $eventName) {
            return $this->events;
        }

        $events = [];
        foreach ($this->events as $event) {
            if ($event instanceof $eventName) {
                $events[] = $event;
            }
        }

        return $events;
    }
}
